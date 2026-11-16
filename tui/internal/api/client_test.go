package api_test

import (
	"context"
	"encoding/json"
	"errors"
	"sync"
	"testing"
	"time"

	"github.com/rjp2525/reno.sh/tui/internal/api"
	"github.com/rjp2525/reno.sh/tui/internal/api/apitest"
)

func newClient(t *testing.T, ttl time.Duration) (*api.Client, *apitest.Server) {
	t.Helper()
	srv := apitest.New()
	t.Cleanup(srv.Close)
	return api.New(api.Options{BaseURL: srv.APIBase(), TTL: ttl, Timeout: 2 * time.Second}), srv
}

func TestSite(t *testing.T) {
	c, _ := newClient(t, time.Minute)
	site, err := c.Site(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if site.Name != "Reno Philibert" || site.SSH.Port != 2222 || len(site.Socials) != 1 {
		t.Fatalf("unexpected site: %+v", site)
	}
	if !site.HasSection("gallery") || site.HasSection("shop") {
		t.Fatalf("HasSection mismatch: %v", site.Sections)
	}
}

func TestAbout(t *testing.T) {
	c, _ := newClient(t, time.Minute)
	a, err := c.About(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(a.Pages) != 3 || a.Pages[0].Section != "professional" {
		t.Fatalf("pages: %+v", a.Pages)
	}
	if a.Experience[0].End != "" || a.Experience[1].End != "2021-12-31" {
		t.Fatalf("experience end dates: %+v", a.Experience)
	}
	if len(a.Experience[0].Responsibilities) != 2 {
		t.Fatalf("responsibilities: %+v", a.Experience[0].Responsibilities)
	}
	if a.Skills[0].Level() != 4 || a.Skills[2].Level() != 2 {
		t.Fatalf("skill levels: %+v", a.Skills)
	}
	if a.Education[0].Achievements[0] != "Dean's list" {
		t.Fatalf("education: %+v", a.Education)
	}
}

func TestPostsAndPost(t *testing.T) {
	c, srv := newClient(t, time.Minute)
	ctx := context.Background()
	posts, err := c.Posts(ctx, api.PostQuery{})
	if err != nil {
		t.Fatal(err)
	}
	if len(posts) != 2 {
		t.Fatalf("want 2 posts, got %d", len(posts))
	}
	p := posts[0]
	if p.Category == nil || p.Category.Slug != "go" || p.Series != nil || p.ReadingTime != 4 || !p.IsFeatured {
		t.Fatalf("post summary: %+v", p)
	}
	if p.PublishedAt.IsZero() || p.PublishedAt.Year() != 2026 {
		t.Fatalf("published_at not parsed: %v", p.PublishedAt)
	}
	if posts[1].Series == nil || posts[1].Series.Order != 1 {
		t.Fatalf("series: %+v", posts[1].Series)
	}

	filtered, err := c.Posts(ctx, api.PostQuery{Category: "laravel"})
	if err != nil {
		t.Fatal(err)
	}
	if len(filtered) != 1 || filtered[0].Slug != "laravel-tips" {
		t.Fatalf("category filter: %+v", filtered)
	}

	post, err := c.Post(ctx, "hello-ssh")
	if err != nil {
		t.Fatal(err)
	}
	if post.Markdown == "" || post.Title != "Hello SSH" || len(post.Blocks) != 1 || post.Blocks[0].Type != "markdown" {
		t.Fatalf("post: %+v", post)
	}
	if srv.Hits("/api/v1/posts") != 2 {
		t.Fatalf("expected 2 list hits (two distinct queries), got %d", srv.Hits("/api/v1/posts"))
	}

	if _, err := c.Post(ctx, "nope"); !errors.Is(err, api.ErrNotFound) {
		t.Fatalf("want ErrNotFound, got %v", err)
	}
}

func TestCategoriesProjectsPhotos(t *testing.T) {
	c, srv := newClient(t, time.Minute)
	ctx := context.Background()

	cats, err := c.BlogCategories(ctx)
	if err != nil || len(cats) != 2 || cats[1].PostsCount != 1 {
		t.Fatalf("categories: %+v %v", cats, err)
	}

	projects, err := c.Projects(ctx, "")
	if err != nil || len(projects) != 2 {
		t.Fatalf("projects: %+v %v", projects, err)
	}
	if projects[0].DemoURL != "" || len(projects[0].Technologies) != 2 || !projects[0].IsOngoing {
		t.Fatalf("project summary: %+v", projects[0])
	}
	prj, err := c.Project(ctx, "reno-sh")
	if err != nil || prj.Markdown == "" || len(prj.Gallery) != 1 {
		t.Fatalf("project: %+v %v", prj, err)
	}

	photos, err := c.Photos(ctx, api.PhotoQuery{})
	if err != nil || len(photos) != 2 {
		t.Fatalf("photos: %+v %v", photos, err)
	}
	if photos[0].ISO.String() != "100" || photos[0].Aperture.String() != "f/8" {
		t.Fatalf("flex exif: iso=%q aperture=%q", photos[0].ISO, photos[0].Aperture)
	}
	if photos[1].Category != nil || photos[1].ExifSummary != "" || !photos[1].TakenAt.IsZero() {
		t.Fatalf("nullable fields: %+v", photos[1])
	}

	ph, err := c.Photo(ctx, "mountain-dawn")
	if err != nil || len(ph.PrintOptions) != 2 || ph.PrintOptions[1].Price != 24900 || ph.PrintOptions[0].WidthIn != 12 {
		t.Fatalf("photo detail: %+v %v", ph, err)
	}

	img, err := c.Image(ctx, ph.ThumbnailURL)
	if err != nil || len(img) == 0 {
		t.Fatalf("image: %v", err)
	}
	if _, err := c.Image(ctx, "file:///etc/passwd"); err == nil {
		t.Fatal("non-http image url must be rejected")
	}

	pcs, err := c.PhotoCategories(ctx)
	if err != nil || len(pcs) != 2 || pcs[0].PhotosCount != 1 {
		t.Fatalf("photo categories: %+v %v", pcs, err)
	}
	_ = srv
}

func TestCacheAvoidsRepeatRequests(t *testing.T) {
	c, srv := newClient(t, time.Minute)
	ctx := context.Background()
	var wg sync.WaitGroup
	for i := 0; i < 20; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			if _, err := c.Site(ctx); err != nil {
				t.Error(err)
			}
		}()
	}
	wg.Wait()
	if _, err := c.Site(ctx); err != nil {
		t.Fatal(err)
	}
	if got := srv.Hits("/api/v1/site"); got != 1 {
		t.Fatalf("want 1 upstream hit, got %d", got)
	}
}

func TestCacheDisabledAndExpiry(t *testing.T) {
	c, srv := newClient(t, 0)
	ctx := context.Background()
	_, _ = c.Site(ctx)
	_, _ = c.Site(ctx)
	if got := srv.Hits("/api/v1/site"); got != 2 {
		t.Fatalf("ttl 0 should not cache, hits=%d", got)
	}

	cache := api.NewCache(20*time.Millisecond, 2)
	cache.Set("a", []byte("1"))
	if _, ok := cache.Get("a"); !ok {
		t.Fatal("expected hit")
	}
	cache.Set("b", []byte("2"))
	cache.Set("c", []byte("3"))
	if cache.Len() > 2 {
		t.Fatalf("cache exceeded bound: %d", cache.Len())
	}
	time.Sleep(30 * time.Millisecond)
	if _, ok := cache.Get("c"); ok {
		t.Fatal("expected expiry")
	}
}

func TestAPIDown(t *testing.T) {
	c, srv := newClient(t, time.Minute)
	srv.SetDown(true)
	_, err := c.Site(context.Background())
	var se *api.StatusError
	if !errors.As(err, &se) || se.Status != 503 {
		t.Fatalf("want StatusError 503, got %v", err)
	}
	// Errors must not be cached.
	srv.SetDown(false)
	if _, err := c.Site(context.Background()); err != nil {
		t.Fatalf("recovered api should succeed: %v", err)
	}
}

func TestUnreachable(t *testing.T) {
	c := api.New(api.Options{BaseURL: "http://127.0.0.1:1/api/v1", Timeout: 500 * time.Millisecond})
	if _, err := c.Site(context.Background()); err == nil {
		t.Fatal("expected connection error")
	}
}

func TestContact(t *testing.T) {
	c, srv := newClient(t, time.Minute)
	ctx := context.Background()

	err := c.Contact(ctx, api.ContactRequest{Name: "Ada", Email: "ada@example.com", Message: "Hello there!"})
	if err != nil {
		t.Fatal(err)
	}
	if len(srv.Contacts) != 1 || srv.Contacts[0]["email"] != "ada@example.com" {
		t.Fatalf("contact not recorded: %+v", srv.Contacts)
	}
	if _, ok := srv.Contacts[0]["subject"]; ok {
		t.Fatal("empty subject should be omitted")
	}

	err = c.Contact(ctx, api.ContactRequest{Name: "Ada", Email: "ada@invalid.test", Message: "x"})
	var ve *api.ValidationError
	if !errors.As(err, &ve) || ve.Field("email") == "" || ve.Field("name") != "" {
		t.Fatalf("want validation error on email, got %#v", err)
	}

	err = c.Contact(ctx, api.ContactRequest{Name: "spam", Email: "a@b.co", Message: "x"})
	if !errors.Is(err, api.ErrThrottled) {
		t.Fatalf("want ErrThrottled, got %v", err)
	}
}

func TestFlexAndStringList(t *testing.T) {
	var v struct {
		A api.Flex       `json:"a"`
		B api.Flex       `json:"b"`
		C api.Flex       `json:"c"`
		L api.StringList `json:"l"`
		S api.StringList `json:"s"`
		T api.Time       `json:"t"`
	}
	in := `{"a": 2.8, "b": 400, "c": null, "l": ["x", {"title": "Thesis", "description": "On things"}, 3], "s": "solo", "t": "2024-02-03"}`
	if err := json.Unmarshal([]byte(in), &v); err != nil {
		t.Fatal(err)
	}
	if v.A != "2.8" || v.B != "400" || v.C != "" {
		t.Fatalf("flex: %+v", v)
	}
	if len(v.L) != 3 || v.L[1] != "Thesis — On things" || v.L[2] != "3" || v.S[0] != "solo" {
		t.Fatalf("stringlist: %+v %+v", v.L, v.S)
	}
	if v.T.Year() != 2024 || v.T.Month() != 2 {
		t.Fatalf("date-only time: %v", v.T)
	}
}
