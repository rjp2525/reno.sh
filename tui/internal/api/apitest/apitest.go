// Package apitest provides an httptest server that mimics the reno.sh API
// (docs/api.md) with canned fixtures, for use in tests.
package apitest

import (
	"bytes"
	"embed"
	"encoding/json"
	"image"
	"image/color"
	"image/jpeg"
	"image/png"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"sync/atomic"
)

//go:embed fixtures/*.json
var fixtures embed.FS

// Server is a fake API.
type Server struct {
	*httptest.Server

	mu       sync.Mutex
	hits     map[string]int
	down     atomic.Bool
	Contacts []map[string]string
}

// New starts a fake API server. Close it when done.
func New() *Server {
	s := &Server{hits: map[string]int{}}
	s.Server = httptest.NewServer(http.HandlerFunc(s.handle))
	return s
}

// APIBase returns the /api/v1 root of the fake server.
func (s *Server) APIBase() string { return s.URL + "/api/v1" }

// SetDown makes every request fail with 503 when true.
func (s *Server) SetDown(v bool) { s.down.Store(v) }

// Hits returns how many times a path (without query) was requested.
func (s *Server) Hits(path string) int {
	s.mu.Lock()
	defer s.mu.Unlock()
	return s.hits[path]
}

var routes = map[string]string{
	"/api/v1/site":                 "site.json",
	"/api/v1/about":                "about.json",
	"/api/v1/posts":                "posts.json",
	"/api/v1/posts/hello-ssh":      "post.json",
	"/api/v1/blog/categories":      "categories.json",
	"/api/v1/projects":             "projects.json",
	"/api/v1/projects/reno-sh":     "project.json",
	"/api/v1/photos":               "photos.json",
	"/api/v1/photos/mountain-dawn": "photo.json",
	"/api/v1/photo-categories":     "photo_categories.json",
}

func (s *Server) handle(w http.ResponseWriter, r *http.Request) {
	s.mu.Lock()
	s.hits[r.URL.Path]++
	s.mu.Unlock()

	if s.down.Load() {
		http.Error(w, `{"message":"Service Unavailable"}`, http.StatusServiceUnavailable)
		return
	}

	switch r.URL.Path {
	case "/img/thumb.png":
		w.Header().Set("Content-Type", "image/png")
		_ = png.Encode(w, TestImage(48, 32))
		return
	case "/img/thumb.jpg":
		w.Header().Set("Content-Type", "image/jpeg")
		_ = jpeg.Encode(w, TestImage(32, 48), nil)
		return
	case "/api/v1/contact":
		s.contact(w, r)
		return
	}

	name, ok := routes[r.URL.Path]
	if !ok || r.Method != http.MethodGet {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusNotFound)
		_, _ = w.Write([]byte(`{"message":"Not Found."}`))
		return
	}
	data, err := fixtures.ReadFile("fixtures/" + name)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	data = bytes.ReplaceAll(data, []byte("{{BASE}}"), []byte(s.URL))

	// Honour the documented filters on the collection fixtures.
	if cat := r.URL.Query().Get("category"); cat != "" && (r.URL.Path == "/api/v1/posts" || r.URL.Path == "/api/v1/photos") {
		data = filterByCategory(data, cat)
	}
	w.Header().Set("Content-Type", "application/json")
	_, _ = w.Write(data)
}

func filterByCategory(data []byte, slug string) []byte {
	var env struct {
		Data []map[string]any `json:"data"`
		Meta map[string]any   `json:"meta"`
	}
	if err := json.Unmarshal(data, &env); err != nil {
		return data
	}
	var out []map[string]any
	for _, item := range env.Data {
		if c, ok := item["category"].(map[string]any); ok && c["slug"] == slug {
			out = append(out, item)
		}
	}
	if out == nil {
		out = []map[string]any{}
	}
	env.Data = out
	env.Meta["total"] = len(out)
	b, _ := json.Marshal(env)
	return b
}

func (s *Server) contact(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}
	var body map[string]string
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		w.WriteHeader(http.StatusBadRequest)
		return
	}
	if body["name"] == "spam" {
		w.WriteHeader(http.StatusTooManyRequests)
		_, _ = w.Write([]byte(`{"message":"Too Many Attempts."}`))
		return
	}
	errs := map[string][]string{}
	if strings.TrimSpace(body["name"]) == "" {
		errs["name"] = []string{"The name field is required."}
	}
	if !strings.Contains(body["email"], "@") || strings.HasSuffix(body["email"], "@invalid.test") {
		errs["email"] = []string{"The email field must be a valid email address."}
	}
	if strings.TrimSpace(body["message"]) == "" {
		errs["message"] = []string{"The message field is required."}
	}
	if len(errs) > 0 {
		w.WriteHeader(http.StatusUnprocessableEntity)
		_ = json.NewEncoder(w).Encode(map[string]any{"message": "The given data was invalid.", "errors": errs})
		return
	}
	s.mu.Lock()
	s.Contacts = append(s.Contacts, body)
	s.mu.Unlock()
	w.WriteHeader(http.StatusAccepted)
	_, _ = w.Write([]byte(`{"message":"Thanks!"}`))
}

// TestImage returns a deterministic gradient image.
func TestImage(w, h int) image.Image {
	img := image.NewRGBA(image.Rect(0, 0, w, h))
	for y := 0; y < h; y++ {
		for x := 0; x < w; x++ {
			img.Set(x, y, color.RGBA{R: uint8(x * 255 / max(w-1, 1)), G: uint8(y * 255 / max(h-1, 1)), B: 0x80, A: 0xff})
		}
	}
	return img
}
