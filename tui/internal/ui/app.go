package ui

import (
	"context"
	"fmt"
	"strings"
	"time"

	"charm.land/bubbles/v2/key"
	tea "charm.land/bubbletea/v2"
	"charm.land/lipgloss/v2"
	"github.com/charmbracelet/colorprofile"
	xansi "github.com/charmbracelet/x/ansi"

	"github.com/rjp2525/reno.sh/tui/internal/ui/theme"
)

type section int

const (
	secHome section = iota
	secAbout
	secProjects
	secPhotos
	secBlog
	secContact
	numSections
)

var sectionLabels = [numSections]string{"_hello", "_about", "_projects", "_photography", "_blog", "_contact-me"}

func (s section) String() string { return sectionLabels[s] }

const (
	headerHeight = 2
	statusHeight = 1
	minWidth     = 40
	minHeight    = 12
)

type idleTickMsg time.Time

// Model is the root Bubble Tea model.
type Model struct {
	env     *Env
	opts    Options
	screens [numSections]Screen
	enabled [numSections]bool
	active  section

	width, height int
	showHelp      bool
	lastInput     time.Time
	idleOut       bool
}

// New creates the root model for one session.
func New(o Options) *Model {
	if o.Context == nil {
		o.Context = context.Background()
	}
	if o.SSHPort == 0 {
		o.SSHPort = 2222
	}
	if o.SSHHost == "" {
		o.SSHHost = "reno.sh"
	}
	env := &Env{
		Client:  o.Client,
		Theme:   theme.New(true),
		Profile: colorprofile.TrueColor,
		SiteURL: strings.TrimRight(o.SiteURL, "/"),
		SSHHost: o.SSHHost,
		SSHPort: o.SSHPort,
		Ctx:     o.Context,
	}
	m := &Model{env: env, opts: o, lastInput: time.Now(), width: 80, height: 24}
	m.screens[secHome] = newHome(env)
	m.screens[secAbout] = newAbout(env)
	m.screens[secProjects] = newProjects(env)
	m.screens[secPhotos] = newPhotos(env)
	m.screens[secBlog] = newBlog(env)
	m.screens[secContact] = newContact(env)
	for i := range m.enabled {
		m.enabled[i] = true
	}
	m.resize()
	return m
}

// Init implements tea.Model.
func (m *Model) Init() tea.Cmd {
	cmds := []tea.Cmd{tea.RequestBackgroundColor, m.loadSite(), m.screens[m.active].Activate()}
	if m.opts.IdleTimeout > 0 {
		cmds = append(cmds, idleTick())
	}
	return tea.Batch(cmds...)
}

func idleTick() tea.Cmd {
	return tea.Tick(15*time.Second, func(t time.Time) tea.Msg { return idleTickMsg(t) })
}

func (m *Model) loadSite() tea.Cmd {
	return func() tea.Msg {
		site, err := m.env.Client.Site(m.env.Ctx)
		return siteMsg{site: site, err: err}
	}
}

// Update implements tea.Model.
func (m *Model) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	switch msg := msg.(type) {
	case tea.WindowSizeMsg:
		if msg.Width > 0 && msg.Height > 0 { // some clients report 0x0; keep 80x24
			m.width, m.height = msg.Width, msg.Height
			m.resize()
		}
		return m, nil

	case tea.BackgroundColorMsg:
		if dark := msg.IsDark(); dark != m.env.Theme.IsDark {
			m.env.Theme = theme.New(dark)
			return m, m.broadcast(themeChangedMsg{})
		}
		return m, nil

	case tea.ColorProfileMsg:
		m.env.Profile = msg.Profile
		return m, m.broadcast(themeChangedMsg{})

	case idleTickMsg:
		if m.opts.IdleTimeout > 0 && time.Since(m.lastInput) >= m.opts.IdleTimeout {
			m.idleOut = true
			return m, tea.Quit
		}
		return m, idleTick()

	case siteMsg:
		if msg.err == nil {
			m.env.Site = msg.site
			m.applySections()
		}
		return m, m.broadcast(msg)

	case goSectionMsg:
		return m, m.switchTo(msg.section)

	case tea.KeyPressMsg:
		m.lastInput = time.Now()
		return m, m.handleKey(msg)

	case tea.MouseMsg:
		m.lastInput = time.Now()
		return m, m.handleMouse(msg)

	case tea.PasteMsg:
		m.lastInput = time.Now()
		return m, m.screens[m.active].Update(msg)
	}

	return m, m.broadcast(msg)
}

// broadcast sends non-input messages to every screen so async results land
// in the right place even after the visitor has moved on.
func (m *Model) broadcast(msg tea.Msg) tea.Cmd {
	cmds := make([]tea.Cmd, 0, numSections)
	for _, s := range m.screens {
		cmds = append(cmds, s.Update(msg))
	}
	return tea.Batch(cmds...)
}

func (m *Model) applySections() {
	site := m.env.Site
	m.enabled[secAbout] = site.HasSection("about")
	m.enabled[secProjects] = site.HasSection("projects")
	m.enabled[secPhotos] = site.HasSection("gallery", "photography", "photos", "prints")
	m.enabled[secBlog] = site.HasSection("blog")
	m.enabled[secContact] = site.HasSection("contact")
	if !m.enabled[m.active] {
		m.active = secHome
	}
}

func (m *Model) visible() []section {
	out := make([]section, 0, numSections)
	for i := range numSections {
		if m.enabled[i] {
			out = append(out, i)
		}
	}
	return out
}

func (m *Model) switchTo(s section) tea.Cmd {
	if s < 0 || s >= numSections || !m.enabled[s] {
		return nil
	}
	m.active = s
	m.showHelp = false
	return m.screens[s].Activate()
}

func (m *Model) cycle(delta int) tea.Cmd {
	vis := m.visible()
	for i, s := range vis {
		if s == m.active {
			return m.switchTo(vis[(i+delta+len(vis))%len(vis)])
		}
	}
	return m.switchTo(secHome)
}

var (
	keyQuit    = key.NewBinding(key.WithKeys("ctrl+c"), key.WithHelp("ctrl+c", "quit"))
	keyHelp    = key.NewBinding(key.WithKeys("?"), key.WithHelp("?", "toggle help"))
	keyNext    = key.NewBinding(key.WithKeys("tab"), key.WithHelp("tab", "next section"))
	keyPrev    = key.NewBinding(key.WithKeys("shift+tab"), key.WithHelp("shift+tab", "prev section"))
	keyBack    = key.NewBinding(key.WithKeys("q", "esc"), key.WithHelp("q/esc", "back"))
	keyJump    = key.NewBinding(key.WithKeys("1", "2", "3", "4", "5", "6"), key.WithHelp("1-6", "jump to section"))
	keyRefresh = key.NewBinding(key.WithKeys("r"), key.WithHelp("r", "retry/refresh"))
)

func globalKeys() []key.Binding {
	return []key.Binding{keyNext, keyPrev, keyJump, keyBack, keyHelp, keyQuit}
}

func (m *Model) handleKey(msg tea.KeyPressMsg) tea.Cmd {
	if key.Matches(msg, keyQuit) {
		return tea.Quit
	}
	if m.showHelp {
		if key.Matches(msg, keyHelp, keyBack) || msg.String() == "enter" {
			m.showHelp = false
		}
		return nil
	}
	scr := m.screens[m.active]
	if scr.Capturing() {
		return scr.Update(msg)
	}
	switch {
	case key.Matches(msg, keyHelp):
		m.showHelp = true
		return nil
	case key.Matches(msg, keyNext):
		return m.cycle(1)
	case key.Matches(msg, keyPrev):
		return m.cycle(-1)
	case key.Matches(msg, keyJump):
		n := int(msg.String()[0] - '1')
		vis := m.visible()
		if n < len(vis) {
			return m.switchTo(vis[n])
		}
		return nil
	case key.Matches(msg, keyBack):
		if scr.Back() {
			return nil
		}
		if m.active != secHome {
			return m.switchTo(secHome)
		}
		if msg.String() == "q" {
			return tea.Quit
		}
		return nil
	}
	return scr.Update(msg)
}

func (m *Model) handleMouse(msg tea.MouseMsg) tea.Cmd {
	if m.showHelp {
		return nil
	}
	if click, ok := msg.(tea.MouseClickMsg); ok && click.Button == tea.MouseLeft && click.Y < headerHeight {
		for _, tab := range m.tabLayout() {
			if click.X >= tab.x0 && click.X < tab.x1 {
				return m.switchTo(tab.sec)
			}
		}
		return nil
	}
	// Translate coordinates into the body area for screens.
	return m.screens[m.active].Update(msg)
}

func (m *Model) bodySize() (int, int) {
	return max(m.width, 1), max(m.height-headerHeight-statusHeight, 1)
}

func (m *Model) resize() {
	w, h := m.bodySize()
	for _, s := range m.screens {
		s.SetSize(w, h)
	}
}

// View implements tea.Model.
func (m *Model) View() tea.View {
	v := tea.NewView(m.render())
	v.AltScreen = true
	v.MouseMode = tea.MouseModeCellMotion
	v.WindowTitle = "reno.sh"
	return v
}

func (m *Model) render() string {
	t := m.env.Theme
	if m.idleOut {
		return t.Faint.Render("// session closed after being idle. come back anytime: ") +
			t.Ident.Render(fmt.Sprintf("ssh %s -p %d", m.env.SSHHost, m.env.SSHPort)) + "\n"
	}
	if m.width < minWidth || m.height < minHeight {
		msg := t.Accent.Render("terminal too small") + "\n" +
			t.Faint.Render(fmt.Sprintf("need %dx%d, have %dx%d", minWidth, minHeight, m.width, m.height))
		return lipgloss.Place(m.width, m.height, lipgloss.Center, lipgloss.Center, msg)
	}
	w, h := m.bodySize()
	body := m.screens[m.active].View()
	if m.showHelp {
		body = m.helpView(w, h)
	}
	body = lipgloss.NewStyle().Width(w).Height(h).MaxHeight(h).MaxWidth(w).Render(body)
	return lipgloss.JoinVertical(lipgloss.Left, m.header(), body, m.statusBar())
}

type tabPos struct {
	sec    section
	x0, x1 int
}

const brand = "reno-philibert"

// tabLayout computes the x-ranges of each tab label in the header.
func (m *Model) tabLayout() []tabPos {
	vis := m.visible()
	x := 1 + len(brand) + 3
	var out []tabPos
	for _, s := range vis {
		lw := lipgloss.Width(s.String()) + 2
		out = append(out, tabPos{sec: s, x0: x, x1: x + lw})
		x += lw + 1
	}
	if len(out) == 0 || out[len(out)-1].x1 > m.width {
		return nil // compact header, no click targets
	}
	return out
}

func (m *Model) header() string {
	t := m.env.Theme
	tabs := m.tabLayout()
	sep := t.Faint.Render("│")
	if tabs == nil {
		// Compact header for narrow terminals.
		vis := m.visible()
		idx := 0
		for i, s := range vis {
			if s == m.active {
				idx = i
			}
		}
		line := " " + t.Ident.Render("reno.sh") + " " + sep + " " +
			t.Faint.Render("‹ ") + t.Selected.Render(m.active.String()) + t.Faint.Render(" ›") +
			t.Faint.Render(fmt.Sprintf("  %d/%d", idx+1, len(vis)))
		rule := t.Faint.Foreground(t.Border).Render(strings.Repeat("─", m.width))
		return xansi.Truncate(line, m.width, "") + "\n" + rule
	}
	var b strings.Builder
	b.WriteString(" " + t.Faint.Render(brand) + " " + sep)
	var activeX0, activeX1 int
	for _, tab := range tabs {
		label := " " + tab.sec.String() + " "
		if tab.sec == m.active {
			b.WriteString(t.Title.Render(label))
			activeX0, activeX1 = tab.x0, tab.x1
		} else {
			b.WriteString(t.Faint.Render(label))
		}
		b.WriteString(sep)
	}
	border := lipgloss.NewStyle().Foreground(t.Border)
	accent := lipgloss.NewStyle().Foreground(t.Orange)
	rule := border.Render(strings.Repeat("─", activeX0)) +
		accent.Render(strings.Repeat("━", activeX1-activeX0)) +
		border.Render(strings.Repeat("─", max(m.width-activeX1, 0)))
	return b.String() + "\n" + rule
}

func (m *Model) statusBar() string {
	t := m.env.Theme
	bar := t.StatusBar
	mode := t.StatusMode.Render(m.active.String())
	crumb := ""
	if c := m.screens[m.active].Crumb(); c != "" {
		crumb = bar.Render(" " + c + " ")
	}
	host, port := m.env.SSHHost, m.env.SSHPort
	if ssh := m.env.Site.SSH; ssh.Host != "" && ssh.Port > 0 {
		host, port = ssh.Host, ssh.Port // what the site advertises wins
	}
	right := t.StatusInfo.Render(fmt.Sprintf("%s • ssh -p %d", host, port))
	hint := bar.Render(" ? help · tab sections · q back ")
	used := lipgloss.Width(mode) + lipgloss.Width(right)
	room := m.width - used
	if room <= 0 {
		return xansi.Truncate(mode+right, m.width, "")
	}
	mid := crumb
	if lipgloss.Width(crumb)+lipgloss.Width(hint) <= room {
		mid = crumb + bar.Render(strings.Repeat(" ", room-lipgloss.Width(crumb)-lipgloss.Width(hint))) + hint
	} else {
		mid = xansi.Truncate(crumb, room, "…")
		mid += bar.Render(strings.Repeat(" ", max(room-lipgloss.Width(mid), 0)))
	}
	return mode + mid + right
}

func (m *Model) helpView(w, h int) string {
	t := m.env.Theme
	row := func(b key.Binding) string {
		hk := b.Help()
		return t.Key.Render(fmt.Sprintf("%-12s", hk.Key)) + " " + t.KeyDesc.Render(hk.Desc)
	}
	var lines []string
	lines = append(lines, t.Comment.Render("// global"))
	for _, b := range globalKeys() {
		lines = append(lines, row(b))
	}
	if ks := m.screens[m.active].Keys(); len(ks) > 0 {
		lines = append(lines, "", t.Comment.Render("// "+m.active.String()))
		for _, b := range ks {
			lines = append(lines, row(b))
		}
	}
	lines = append(lines, "", t.Faint.Render("mouse wheel scrolls · click tabs to switch"))
	box := t.Box.BorderForeground(t.Primary).Padding(1, 3).Render(
		t.Heading.Render("keyboard shortcuts") + "\n\n" + strings.Join(lines, "\n"))
	return lipgloss.Place(w, h, lipgloss.Center, lipgloss.Center, box)
}

// IdleTimedOut reports whether the session ended because of inactivity.
func (m *Model) IdleTimedOut() bool { return m.idleOut }
