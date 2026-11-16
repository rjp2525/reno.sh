package api

import (
	"bytes"
	"encoding/json"
	"strconv"
	"strings"
	"time"
)

// Meta is the pagination block returned with collections.
type Meta struct {
	CurrentPage int `json:"current_page"`
	LastPage    int `json:"last_page"`
	PerPage     int `json:"per_page"`
	Total       int `json:"total"`
}

// Social is a social profile link.
type Social struct {
	Label string `json:"label"`
	URL   string `json:"url"`
}

// SSHInfo advertises the SSH endpoint.
type SSHInfo struct {
	Host string `json:"host"`
	Port int    `json:"port"`
}

// Site is the response of GET /site.
type Site struct {
	Name     string   `json:"name"`
	Title    string   `json:"title"`
	Tagline  string   `json:"tagline"`
	Location string   `json:"location"`
	Email    string   `json:"email"`
	URL      string   `json:"url"`
	Socials  []Social `json:"socials"`
	SSH      SSHInfo  `json:"ssh"`
	Sections []string `json:"sections"`
}

// HasSection reports whether the site advertises a section. An empty list
// means "everything is enabled".
func (s Site) HasSection(names ...string) bool {
	if len(s.Sections) == 0 {
		return true
	}
	for _, have := range s.Sections {
		for _, n := range names {
			if strings.EqualFold(have, n) {
				return true
			}
		}
	}
	return false
}

// AboutPage is one markdown page in the about section.
type AboutPage struct {
	Section  string `json:"section"`
	Slug     string `json:"slug"`
	Title    string `json:"title"`
	Icon     string `json:"icon"`
	Text     string `json:"text"`
	Markdown string `json:"markdown"`
}

// Experience is a job on the timeline.
type Experience struct {
	ID               string     `json:"id"`
	Title            string     `json:"title"`
	Company          string     `json:"company"`
	Location         string     `json:"location"`
	Start            string     `json:"start"`
	End              string     `json:"end"`
	Responsibilities StringList `json:"responsibilities"`
}

// Skill is a single skill with proficiency.
type Skill struct {
	ID          string `json:"id"`
	Category    string `json:"category"`
	Name        string `json:"name"`
	Proficiency string `json:"proficiency"`
	Started     string `json:"started"`
	Ended       string `json:"ended"`
}

// Level maps proficiency to 1..4 (0 when unknown).
func (s Skill) Level() int {
	switch strings.ToLower(s.Proficiency) {
	case "beginner":
		return 1
	case "intermediate":
		return 2
	case "advanced":
		return 3
	case "expert":
		return 4
	}
	return 0
}

// Education is a school entry.
type Education struct {
	ID               string     `json:"id"`
	SchoolName       string     `json:"school_name"`
	Location         string     `json:"location"`
	Degree           string     `json:"degree"`
	Minor            string     `json:"minor"`
	Started          string     `json:"started"`
	Graduated        string     `json:"graduated"`
	Description      string     `json:"description"`
	Achievements     StringList `json:"achievements"`
	Projects         StringList `json:"projects"`
	Extracurriculars StringList `json:"extracurriculars"`
}

// About is the response of GET /about.
type About struct {
	Pages      []AboutPage  `json:"pages"`
	Experience []Experience `json:"experience"`
	Skills     []Skill      `json:"skills"`
	Education  []Education  `json:"education"`
}

// Category is a blog category reference or listing entry.
type Category struct {
	ID          string `json:"id"`
	Name        string `json:"name"`
	Slug        string `json:"slug"`
	Description string `json:"description"`
	Color       string `json:"color"`
	PostsCount  int    `json:"posts_count"`
	PhotosCount int    `json:"photos_count"`
}

// Series is a post series reference.
type Series struct {
	ID    string `json:"id"`
	Title string `json:"title"`
	Slug  string `json:"slug"`
	Order int    `json:"order"`
}

// PostSummary is a post as listed in GET /posts.
type PostSummary struct {
	ID               string    `json:"id"`
	Title            string    `json:"title"`
	Slug             string    `json:"slug"`
	Excerpt          string    `json:"excerpt"`
	URL              string    `json:"url"`
	FeaturedImageURL string    `json:"featured_image_url"`
	ReadingTime      int       `json:"reading_time"`
	PublishedAt      Time      `json:"published_at"`
	IsFeatured       bool      `json:"is_featured"`
	Category         *Category `json:"category"`
	Series           *Series   `json:"series"`
	Tags             []string  `json:"tags"`
}

// Post is a full post from GET /posts/{slug}.
type Post struct {
	PostSummary
	Blocks          []Block `json:"blocks"`
	Text            string  `json:"text"`
	Markdown        string  `json:"markdown"`
	MetaTitle       string  `json:"meta_title"`
	MetaDescription string  `json:"meta_description"`
}

// Block is a rich content block. The TUI renders the document-level
// markdown/text fields, so blocks are kept raw.
type Block struct {
	Type string          `json:"type"`
	Data json.RawMessage `json:"data"`
}

// Technology is a project tech tag.
type Technology struct {
	Name  string `json:"name"`
	Slug  string `json:"slug"`
	Color string `json:"color"`
}

// ProjectSummary is a project as listed in GET /projects.
type ProjectSummary struct {
	ID               string       `json:"id"`
	Title            string       `json:"title"`
	Slug             string       `json:"slug"`
	Summary          string       `json:"summary"`
	Type             string       `json:"type"`
	Status           string       `json:"status"`
	URL              string       `json:"url"`
	GithubURL        string       `json:"github_url"`
	DemoURL          string       `json:"demo_url"`
	FeaturedImageURL string       `json:"featured_image_url"`
	StartDate        string       `json:"start_date"`
	EndDate          string       `json:"end_date"`
	IsOngoing        bool         `json:"is_ongoing"`
	IsFeatured       bool         `json:"is_featured"`
	Technologies     []Technology `json:"technologies"`
	WebURL           string       `json:"web_url"`
}

// Project is a full project from GET /projects/{slug}.
type Project struct {
	ProjectSummary
	DescriptionHTML string   `json:"description_html"`
	Text            string   `json:"text"`
	Markdown        string   `json:"markdown"`
	Gallery         []string `json:"gallery"`
}

// PhotoCategory is a photo category reference or listing entry.
type PhotoCategory struct {
	ID          string `json:"id"`
	Name        string `json:"name"`
	Slug        string `json:"slug"`
	Color       string `json:"color"`
	PhotosCount int    `json:"photos_count"`
}

// Photo is a photo as listed in GET /photos.
type Photo struct {
	ID              string         `json:"id"`
	Title           string         `json:"title"`
	Slug            string         `json:"slug"`
	DescriptionHTML string         `json:"description_html"`
	Text            string         `json:"text"`
	WebURL          string         `json:"web_url"`
	ThumbnailURL    string         `json:"thumbnail_url"`
	OGImageURL      string         `json:"og_image_url"`
	Width           int            `json:"width"`
	Height          int            `json:"height"`
	TakenAt         Time           `json:"taken_at"`
	ExifSummary     string         `json:"exif_summary"`
	CameraBody      Flex           `json:"camera_body"`
	Lens            Flex           `json:"lens"`
	FocalLength     Flex           `json:"focal_length"`
	Aperture        Flex           `json:"aperture"`
	ShutterSpeed    Flex           `json:"shutter_speed"`
	ISO             Flex           `json:"iso"`
	Category        *PhotoCategory `json:"category"`
	Tags            []string       `json:"tags"`
	IsFavorite      bool           `json:"is_favorite"`
	PrintsAvailable bool           `json:"prints_available"`
	URL             string         `json:"url"`
}

// PhotoDetail is GET /photos/{slug}.
type PhotoDetail struct {
	Photo
	PrintOptions []PrintOption `json:"print_options"`
}

// PrintOption is a purchasable print product for a photo.
type PrintOption struct {
	ProductID   string         `json:"product_id"`
	Name        string         `json:"name"`
	Type        string         `json:"type"`
	SizeLabel   string         `json:"size_label"`
	WidthIn     float64        `json:"width_in"`
	HeightIn    float64        `json:"height_in"`
	Price       int64          `json:"price"`
	Currency    string         `json:"currency"`
	Description string         `json:"description"`
	Attributes  map[string]any `json:"attributes"`
}

// ContactRequest is the body of POST /contact.
type ContactRequest struct {
	Name    string `json:"name"`
	Email   string `json:"email"`
	Subject string `json:"subject,omitempty"`
	Message string `json:"message"`
}

// Time is a nullable ISO-8601 timestamp that tolerates date-only values.
type Time struct{ time.Time }

// UnmarshalJSON accepts null, RFC 3339 timestamps and YYYY-MM-DD dates.
func (t *Time) UnmarshalJSON(b []byte) error {
	if bytes.Equal(b, []byte("null")) {
		t.Time = time.Time{}
		return nil
	}
	var s string
	if err := json.Unmarshal(b, &s); err != nil {
		return err
	}
	t.Time = ParseTime(s)
	return nil
}

// ParseTime parses the timestamp formats the API may use, returning the zero
// time when the value is empty or unparseable.
func ParseTime(s string) time.Time {
	s = strings.TrimSpace(s)
	if s == "" {
		return time.Time{}
	}
	for _, layout := range []string{time.RFC3339Nano, time.RFC3339, "2006-01-02T15:04:05", "2006-01-02 15:04:05", "2006-01-02", "2006-01"} {
		if v, err := time.Parse(layout, s); err == nil {
			return v.UTC()
		}
	}
	return time.Time{}
}

// Flex decodes a JSON string, number or bool into its string form. EXIF
// fields are untyped in the API documentation, so accept either.
type Flex string

// UnmarshalJSON implements json.Unmarshaler.
func (f *Flex) UnmarshalJSON(b []byte) error {
	b = bytes.TrimSpace(b)
	switch {
	case bytes.Equal(b, []byte("null")):
		*f = ""
	case len(b) > 0 && b[0] == '"':
		var s string
		if err := json.Unmarshal(b, &s); err != nil {
			return err
		}
		*f = Flex(s)
	default:
		var n json.Number
		if err := json.Unmarshal(b, &n); err == nil {
			if fl, ferr := n.Float64(); ferr == nil && fl == float64(int64(fl)) {
				*f = Flex(strconv.FormatInt(int64(fl), 10))
			} else {
				*f = Flex(n.String())
			}
			return nil
		}
		*f = Flex(string(b))
	}
	return nil
}

// String returns the value.
func (f Flex) String() string { return string(f) }

// StringList decodes an array whose items are strings or objects. Objects
// are reduced to their most descriptive string field. A lone string becomes
// a one-element list.
type StringList []string

// UnmarshalJSON implements json.Unmarshaler.
func (l *StringList) UnmarshalJSON(b []byte) error {
	b = bytes.TrimSpace(b)
	if bytes.Equal(b, []byte("null")) {
		*l = nil
		return nil
	}
	if len(b) > 0 && b[0] == '"' {
		var s string
		if err := json.Unmarshal(b, &s); err != nil {
			return err
		}
		*l = StringList{s}
		return nil
	}
	var raw []json.RawMessage
	if err := json.Unmarshal(b, &raw); err != nil {
		return err
	}
	out := make(StringList, 0, len(raw))
	for _, item := range raw {
		item = bytes.TrimSpace(item)
		if len(item) == 0 {
			continue
		}
		switch item[0] {
		case '"':
			var s string
			if err := json.Unmarshal(item, &s); err != nil {
				return err
			}
			out = append(out, s)
		case '{':
			var m map[string]any
			if err := json.Unmarshal(item, &m); err != nil {
				return err
			}
			out = append(out, describe(m))
		default:
			var f Flex
			if err := f.UnmarshalJSON(item); err != nil {
				return err
			}
			out = append(out, f.String())
		}
	}
	*l = out
	return nil
}

func describe(m map[string]any) string {
	var parts []string
	for _, k := range []string{"title", "name", "label", "text", "description"} {
		if v, ok := m[k].(string); ok && strings.TrimSpace(v) != "" {
			parts = append(parts, strings.TrimSpace(v))
			if len(parts) == 2 {
				break
			}
		}
	}
	return strings.Join(parts, " — ")
}
