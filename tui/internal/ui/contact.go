package ui

import (
	"errors"
	"net/mail"
	"strings"
	"unicode/utf8"

	"charm.land/bubbles/v2/key"
	"charm.land/bubbles/v2/textarea"
	"charm.land/bubbles/v2/textinput"
	tea "charm.land/bubbletea/v2"
	"charm.land/lipgloss/v2"
	xansi "github.com/charmbracelet/x/ansi"

	"github.com/rjp2525/reno.sh/tui/internal/api"
)

const (
	fieldName = iota
	fieldEmail
	fieldSubject
	fieldMessage
	fieldSubmit
	numFields
)

var fieldLabels = [numFields]string{"name", "email", "subject", "message", ""}

const (
	maxName    = 255
	maxSubject = 255
	maxMessage = 5000
	minMessage = 10
)

type contactSentMsg struct{ err error }

type contact struct {
	env  *Env
	w, h int

	inputs  [3]textinput.Model // name, email, subject
	message textarea.Model
	focus   int
	errs    map[int]string
	formErr string
	load    loader
	sent    bool
	sentTo  string
	editing bool // true while a field has focus (captures keys)
}

func newContact(env *Env) *contact {
	c := &contact{env: env, load: newLoader(env), errs: map[int]string{}}
	placeholders := []string{"Ada Lovelace", "ada@example.com", "optional"}
	limits := []int{maxName, 254, maxSubject}
	for i := range c.inputs {
		ti := textinput.New()
		ti.Prompt = ""
		ti.Placeholder = placeholders[i]
		ti.CharLimit = limits[i]
		c.inputs[i] = ti
	}
	ta := textarea.New()
	ta.Placeholder = "Hey Reno, I saw your site over SSH and…"
	ta.ShowLineNumbers = false
	ta.CharLimit = maxMessage
	ta.Prompt = "│ "
	c.message = ta
	c.applyStyles()
	return c
}

func (c *contact) applyStyles() {
	t := c.env.Theme
	is := textinput.DefaultStyles(t.IsDark)
	is.Focused.Text = lipgloss.NewStyle().Foreground(t.Bright)
	is.Focused.Placeholder = lipgloss.NewStyle().Foreground(t.Border)
	is.Blurred.Text = lipgloss.NewStyle().Foreground(t.Text)
	is.Blurred.Placeholder = lipgloss.NewStyle().Foreground(t.Border)
	is.Cursor.Color = t.Primary
	for i := range c.inputs {
		c.inputs[i].SetStyles(is)
	}
	as := textarea.DefaultStyles(t.IsDark)
	as.Focused.Text = lipgloss.NewStyle().Foreground(t.Bright)
	as.Focused.Prompt = lipgloss.NewStyle().Foreground(t.Primary)
	as.Focused.CursorLine = lipgloss.NewStyle()
	as.Focused.Placeholder = lipgloss.NewStyle().Foreground(t.Border)
	as.Blurred.Text = lipgloss.NewStyle().Foreground(t.Text)
	as.Blurred.Prompt = lipgloss.NewStyle().Foreground(t.Border)
	as.Blurred.Placeholder = lipgloss.NewStyle().Foreground(t.Border)
	as.Cursor.Color = t.Primary
	c.message.SetStyles(as)
}

// Activate does not grab focus so tab keeps cycling sections; enter starts
// editing the highlighted field.
func (c *contact) Activate() tea.Cmd { return nil }

func (c *contact) SetSize(w, h int) {
	c.w, c.h = w, h
	fw := c.fieldWidth()
	for i := range c.inputs {
		c.inputs[i].SetWidth(fw)
	}
	c.message.SetWidth(fw)
	c.message.SetHeight(max(min(h-18, 10), 3))
}

func (c *contact) fieldWidth() int { return max(min(c.w-16, 72), 20) }

// Back leaves the form: the first esc blurs the field, the next goes home.
func (c *contact) Back() bool {
	if c.editing {
		c.blurAll()
		return true
	}
	return false
}

func (c *contact) Capturing() bool { return c.editing && !c.sent && !c.load.loading }

func (c *contact) Crumb() string {
	if c.sent {
		return "sent ✓"
	}
	return ""
}

func (c *contact) Keys() []key.Binding {
	return []key.Binding{
		key.NewBinding(key.WithKeys("tab"), key.WithHelp("tab/shift+tab", "next/prev field (while editing)")),
		key.NewBinding(key.WithKeys("enter"), key.WithHelp("enter", "edit field / submit")),
		key.NewBinding(key.WithKeys("ctrl+s"), key.WithHelp("ctrl+s", "send message")),
		key.NewBinding(key.WithKeys("esc"), key.WithHelp("esc", "stop editing, then back")),
		key.NewBinding(key.WithKeys("n"), key.WithHelp("n", "new message (after sending)")),
	}
}

func (c *contact) blurAll() {
	for i := range c.inputs {
		c.inputs[i].Blur()
	}
	c.message.Blur()
	c.editing = false
}

func (c *contact) setFocus(i int) tea.Cmd {
	c.blurAll()
	c.focus = (i + numFields) % numFields
	switch {
	case c.focus < fieldMessage:
		c.editing = true
		return c.inputs[c.focus].Focus()
	case c.focus == fieldMessage:
		c.editing = true
		return c.message.Focus()
	}
	return nil // submit button: not capturing, so global keys work
}

func (c *contact) values() api.ContactRequest {
	return api.ContactRequest{
		Name:    strings.TrimSpace(c.inputs[fieldName].Value()),
		Email:   strings.TrimSpace(c.inputs[fieldEmail].Value()),
		Subject: strings.TrimSpace(c.inputs[fieldSubject].Value()),
		Message: strings.TrimSpace(c.message.Value()),
	}
}

// validate mirrors the server rules closely enough to catch typos early.
func validateContact(r api.ContactRequest) map[int]string {
	errs := map[int]string{}
	switch {
	case r.Name == "":
		errs[fieldName] = "name is required"
	case utf8.RuneCountInString(r.Name) > maxName:
		errs[fieldName] = "name is too long"
	}
	if r.Email == "" {
		errs[fieldEmail] = "email is required"
	} else if a, err := mail.ParseAddress(r.Email); err != nil || a.Address != r.Email || !strings.Contains(r.Email[strings.LastIndex(r.Email, "@")+1:], ".") {
		errs[fieldEmail] = "that doesn't look like an email address"
	}
	if utf8.RuneCountInString(r.Subject) > maxSubject {
		errs[fieldSubject] = "subject is too long"
	}
	switch n := utf8.RuneCountInString(r.Message); {
	case n == 0:
		errs[fieldMessage] = "message is required"
	case n < minMessage:
		errs[fieldMessage] = "message is a bit short (10+ characters)"
	case n > maxMessage:
		errs[fieldMessage] = "message is too long (5000 max)"
	}
	return errs
}

func (c *contact) submit() tea.Cmd {
	req := c.values()
	c.formErr = ""
	c.errs = validateContact(req)
	if len(c.errs) > 0 {
		for i := range numFields {
			if _, bad := c.errs[i]; bad {
				return c.setFocus(i)
			}
		}
	}
	c.blurAll()
	return tea.Batch(c.load.start(), func() tea.Msg {
		return contactSentMsg{c.env.Client.Contact(c.env.Ctx, req)}
	})
}

func (c *contact) reset() tea.Cmd {
	for i := range c.inputs {
		c.inputs[i].Reset()
	}
	c.message.Reset()
	c.sent, c.sentTo, c.formErr = false, "", ""
	c.errs = map[int]string{}
	return c.setFocus(fieldName)
}

func (c *contact) Update(msg tea.Msg) tea.Cmd {
	if cmd := c.load.update(msg); cmd != nil {
		return cmd
	}
	switch msg := msg.(type) {
	case themeChangedMsg:
		c.applyStyles()
		return nil
	case contactSentMsg:
		c.load.done(nil)
		if msg.err == nil {
			c.sent, c.sentTo = true, c.values().Email
			return nil
		}
		var ve *api.ValidationError
		if errors.As(msg.err, &ve) {
			for i, name := range fieldLabels[:fieldSubmit] {
				if e := ve.Field(name); e != "" {
					c.errs[i] = strings.ToLower(strings.TrimSuffix(e, "."))
				}
			}
			c.formErr = "please fix the highlighted fields"
		} else {
			c.formErr = friendlyError(msg.err)
		}
		return nil
	case tea.KeyPressMsg:
		return c.handleKey(msg)
	case tea.PasteMsg:
		return c.forward(msg)
	}
	return c.forward(msg)
}

func (c *contact) handleKey(msg tea.KeyPressMsg) tea.Cmd {
	if c.load.loading {
		return nil
	}
	if c.sent {
		if msg.String() == "n" {
			return c.reset()
		}
		return nil
	}
	switch msg.String() {
	case "esc":
		if c.editing {
			c.blurAll()
			return nil
		}
		return goSection(secHome)
	case "ctrl+s":
		return c.submit()
	case "tab", "ctrl+n":
		if c.editing {
			return c.setFocus(c.focus + 1)
		}
	case "shift+tab", "ctrl+p":
		if c.editing {
			return c.setFocus(c.focus - 1)
		}
	case "down":
		if c.focus < fieldMessage || !c.editing {
			return c.setFocus(c.focus + 1)
		}
	case "up":
		if c.focus < fieldMessage || !c.editing {
			return c.setFocus(c.focus - 1)
		}
	case "enter":
		if !c.editing {
			if c.focus == fieldSubmit {
				return c.submit()
			}
			return c.setFocus(c.focus)
		}
		if c.focus < fieldMessage {
			return c.setFocus(c.focus + 1)
		}
	case "j", "k":
		if !c.editing {
			d := 1
			if msg.String() == "k" {
				d = -1
			}
			return c.setFocus(c.focus + d)
		}
	}
	if !c.editing {
		return nil
	}
	delete(c.errs, c.focus)
	return c.forward(msg)
}

func (c *contact) forward(msg tea.Msg) tea.Cmd {
	var cmd tea.Cmd
	switch {
	case c.focus < fieldMessage:
		c.inputs[c.focus], cmd = c.inputs[c.focus].Update(msg)
	case c.focus == fieldMessage:
		c.message, cmd = c.message.Update(msg)
	}
	return cmd
}

func (c *contact) View() string {
	t := c.env.Theme
	fw := c.fieldWidth()
	if c.sent {
		msg := t.Success.Render("✓ message sent") + "\n\n" +
			t.Body.Render("thanks! I'll get back to you at ") + t.Accent.Render(c.sentTo) + "\n\n" +
			t.Keyword.Render("const ") + t.Ident.Render("status") + t.Body.Render(" = ") + t.String.Render(`"delivered"`) + t.Body.Render(";") + "\n\n" +
			t.Faint.Render("n new message · tab other sections · q back")
		return lipgloss.Place(c.w, c.h, lipgloss.Center, lipgloss.Center, t.Box.Padding(1, 3).Render(msg))
	}

	var b strings.Builder
	b.WriteString(t.Comment.Render("// send me a message, it lands straight in my inbox") + "\n\n")
	label := func(i int) string {
		l := "_" + fieldLabels[i] + ":"
		if i == c.focus {
			return t.Selected.Render(l)
		}
		return t.Faint.Render(l)
	}
	field := func(i int, view string) {
		border := t.Border
		if i == c.focus {
			border = t.Primary
		}
		if _, bad := c.errs[i]; bad {
			border = t.Red
		}
		box := lipgloss.NewStyle().Border(lipgloss.RoundedBorder()).BorderForeground(border).Padding(0, 1).Width(fw + 4)
		b.WriteString(label(i) + "\n" + box.Render(view) + "\n")
		if e, bad := c.errs[i]; bad {
			b.WriteString(t.Error.Render("  ✗ "+e) + "\n")
		}
	}
	for i := range c.inputs {
		field(i, c.inputs[i].View())
	}
	field(fieldMessage, c.message.View())

	b.WriteString("\n")
	btn := " submit-message "
	if c.focus == fieldSubmit {
		b.WriteString(lipgloss.NewStyle().Foreground(lipgloss.Color("#011627")).Background(t.Orange).Bold(true).Render(btn))
	} else {
		b.WriteString(lipgloss.NewStyle().Foreground(t.Bright).Background(t.Border).Render(btn))
	}
	switch {
	case c.load.loading:
		b.WriteString("  " + c.load.spin.View() + t.Faint.Render(" sending…"))
	case c.formErr != "":
		b.WriteString("  " + t.Error.Render("✗ "+c.formErr))
	}
	hint := "enter edit · tab next field · ctrl+s send · esc done"
	if !c.editing {
		hint = "↑/↓ choose field · enter edit/submit · ctrl+s send"
	}
	b.WriteString("\n\n" + t.Faint.Render(hint))

	content := b.String()
	lines := strings.Split(content, "\n")
	// Keep the focused field on screen on short terminals.
	if len(lines) > c.h {
		start := 0
		if c.focus >= fieldMessage {
			start = len(lines) - c.h
		}
		lines = lines[start : start+c.h]
	}
	for i := range lines {
		lines[i] = xansi.Truncate(lines[i], c.w-4, "")
	}
	return lipgloss.NewStyle().Padding(0, 2).Render(strings.Join(lines, "\n"))
}
