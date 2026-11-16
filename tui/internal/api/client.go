// Package api is a small client for the reno.sh public JSON API (v1) with a
// shared in-memory response cache so many concurrent SSH sessions only cost
// the site one request per resource per TTL.
package api

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"

	"golang.org/x/sync/singleflight"
)

const (
	maxJSONBytes  = 4 << 20 // 4 MiB
	maxImageBytes = 8 << 20 // 8 MiB
	maxPages      = 10
	perPage       = 100
)

// ErrNotFound is returned for 404 responses.
var ErrNotFound = errors.New("not found")

// ErrThrottled is returned for 429 responses.
var ErrThrottled = errors.New("too many requests, slow down")

// ValidationError is a Laravel-style 422 response.
type ValidationError struct {
	Message string              `json:"message"`
	Errors  map[string][]string `json:"errors"`
}

func (e *ValidationError) Error() string {
	if e.Message != "" {
		return e.Message
	}
	return "validation failed"
}

// Field returns the first error for a field, or "".
func (e *ValidationError) Field(name string) string {
	if msgs := e.Errors[name]; len(msgs) > 0 {
		return msgs[0]
	}
	return ""
}

// StatusError is any other non-2xx response.
type StatusError struct {
	Status int
	URL    string
}

func (e *StatusError) Error() string {
	return fmt.Sprintf("api returned %d %s", e.Status, http.StatusText(e.Status))
}

// Options configure a Client.
type Options struct {
	BaseURL    string        // e.g. https://reno.sh/api/v1
	TTL        time.Duration // cache TTL for GET responses
	Timeout    time.Duration // per request timeout
	HTTPClient *http.Client  // optional
	UserAgent  string
}

// Client talks to the API. It is safe for concurrent use and is meant to be
// shared by every session in the process.
type Client struct {
	base    string
	http    *http.Client
	timeout time.Duration
	ua      string
	cache   *Cache
	group   singleflight.Group
}

// New creates a Client.
func New(o Options) *Client {
	if o.Timeout == 0 {
		o.Timeout = 10 * time.Second
	}
	hc := o.HTTPClient
	if hc == nil {
		hc = &http.Client{Timeout: o.Timeout}
	}
	if o.UserAgent == "" {
		o.UserAgent = "reno.sh-tui/1"
	}
	return &Client{
		base:    strings.TrimRight(o.BaseURL, "/"),
		timeout: o.Timeout,
		http:    hc,
		ua:      o.UserAgent,
		cache:   NewCache(o.TTL, 512),
	}
}

// Cache exposes the response cache (for tests and metrics).
func (c *Client) Cache() *Cache { return c.cache }

// Site fetches GET /site.
func (c *Client) Site(ctx context.Context) (Site, error) {
	var env struct{ Data Site }
	err := c.getJSON(ctx, "/site", nil, &env)
	return env.Data, err
}

// About fetches GET /about.
func (c *Client) About(ctx context.Context) (About, error) {
	var env struct{ Data About }
	err := c.getJSON(ctx, "/about", nil, &env)
	return env.Data, err
}

// PostQuery filters GET /posts.
type PostQuery struct {
	Category string
	Series   string
	Tag      string
	Q        string
}

func (q PostQuery) values() url.Values {
	v := url.Values{}
	set(v, "category", q.Category)
	set(v, "series", q.Series)
	set(v, "tag", q.Tag)
	set(v, "q", q.Q)
	return v
}

// Posts fetches every page of GET /posts (bounded) for the query.
func (c *Client) Posts(ctx context.Context, q PostQuery) ([]PostSummary, error) {
	return collectPages[PostSummary](ctx, c, "/posts", q.values())
}

// Post fetches GET /posts/{slug}.
func (c *Client) Post(ctx context.Context, slug string) (Post, error) {
	var env struct{ Data Post }
	err := c.getJSON(ctx, "/posts/"+url.PathEscape(slug), nil, &env)
	return env.Data, err
}

// BlogCategories fetches GET /blog/categories.
func (c *Client) BlogCategories(ctx context.Context) ([]Category, error) {
	var env struct{ Data []Category }
	err := c.getJSON(ctx, "/blog/categories", nil, &env)
	return env.Data, err
}

// Projects fetches GET /projects, optionally filtered by type.
func (c *Client) Projects(ctx context.Context, typ string) ([]ProjectSummary, error) {
	v := url.Values{}
	set(v, "type", typ)
	return collectPages[ProjectSummary](ctx, c, "/projects", v)
}

// Project fetches GET /projects/{slug}.
func (c *Client) Project(ctx context.Context, slug string) (Project, error) {
	var env struct{ Data Project }
	err := c.getJSON(ctx, "/projects/"+url.PathEscape(slug), nil, &env)
	return env.Data, err
}

// PhotoQuery filters GET /photos.
type PhotoQuery struct {
	Category  string
	Favorites bool
	Printable bool
}

// Photos fetches every page of GET /photos (bounded) for the query.
func (c *Client) Photos(ctx context.Context, q PhotoQuery) ([]Photo, error) {
	v := url.Values{}
	set(v, "category", q.Category)
	if q.Favorites {
		v.Set("favorites", "1")
	}
	if q.Printable {
		v.Set("printable", "1")
	}
	return collectPages[Photo](ctx, c, "/photos", v)
}

// Photo fetches GET /photos/{slug}.
func (c *Client) Photo(ctx context.Context, slug string) (PhotoDetail, error) {
	var env struct{ Data PhotoDetail }
	err := c.getJSON(ctx, "/photos/"+url.PathEscape(slug), nil, &env)
	return env.Data, err
}

// PhotoCategories fetches GET /photo-categories.
func (c *Client) PhotoCategories(ctx context.Context) ([]PhotoCategory, error) {
	var env struct{ Data []PhotoCategory }
	err := c.getJSON(ctx, "/photo-categories", nil, &env)
	return env.Data, err
}

// Contact sends POST /contact. Never cached.
func (c *Client) Contact(ctx context.Context, req ContactRequest) error {
	body, err := json.Marshal(req)
	if err != nil {
		return err
	}
	r, err := http.NewRequestWithContext(ctx, http.MethodPost, c.base+"/contact", bytes.NewReader(body))
	if err != nil {
		return err
	}
	r.Header.Set("Content-Type", "application/json")
	c.headers(r)
	resp, err := c.http.Do(r)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	data, _ := io.ReadAll(io.LimitReader(resp.Body, maxJSONBytes))
	if resp.StatusCode >= 200 && resp.StatusCode < 300 {
		return nil
	}
	return statusErr(resp.StatusCode, r.URL.String(), data)
}

// Image downloads (and caches) an image by absolute URL. Only http(s) URLs
// are accepted.
func (c *Client) Image(ctx context.Context, raw string) ([]byte, error) {
	u, err := url.Parse(raw)
	if err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Host == "" {
		return nil, fmt.Errorf("invalid image url")
	}
	return c.fetch(ctx, u.String(), "image/*", maxImageBytes)
}

func (c *Client) getJSON(ctx context.Context, path string, q url.Values, out any) error {
	u := c.base + path
	if len(q) > 0 {
		u += "?" + q.Encode() // Encode sorts keys, giving stable cache keys.
	}
	data, err := c.fetch(ctx, u, "application/json", maxJSONBytes)
	if err != nil {
		return err
	}
	if err := json.Unmarshal(data, out); err != nil {
		return fmt.Errorf("decode %s: %w", path, err)
	}
	return nil
}

// fetch performs a cached, de-duplicated GET.
func (c *Client) fetch(ctx context.Context, u, accept string, limit int64) ([]byte, error) {
	if b, ok := c.cache.Get(u); ok {
		return b, nil
	}
	// Collapse concurrent identical requests from many sessions. The shared
	// request is detached from any single caller's context so one visitor
	// disconnecting does not fail everybody else's load.
	ch := c.group.DoChan(u, func() (any, error) {
		reqCtx, cancel := context.WithTimeout(context.WithoutCancel(ctx), c.timeout)
		defer cancel()
		r, err := http.NewRequestWithContext(reqCtx, http.MethodGet, u, nil)
		if err != nil {
			return nil, err
		}
		c.headers(r)
		r.Header.Set("Accept", accept)
		resp, err := c.http.Do(r)
		if err != nil {
			return nil, err
		}
		defer resp.Body.Close()
		data, err := io.ReadAll(io.LimitReader(resp.Body, limit+1))
		if err != nil {
			return nil, err
		}
		if int64(len(data)) > limit {
			return nil, fmt.Errorf("response too large")
		}
		if resp.StatusCode < 200 || resp.StatusCode >= 300 {
			return nil, statusErr(resp.StatusCode, u, data)
		}
		c.cache.Set(u, data)
		return data, nil
	})
	select {
	case <-ctx.Done():
		return nil, ctx.Err()
	case res := <-ch:
		if res.Err != nil {
			return nil, res.Err
		}
		return res.Val.([]byte), nil
	}
}

func (c *Client) headers(r *http.Request) {
	r.Header.Set("Accept", "application/json")
	r.Header.Set("User-Agent", c.ua)
}

func statusErr(code int, u string, body []byte) error {
	switch code {
	case http.StatusNotFound:
		return ErrNotFound
	case http.StatusTooManyRequests:
		return ErrThrottled
	case http.StatusUnprocessableEntity:
		ve := &ValidationError{}
		if err := json.Unmarshal(body, ve); err != nil || (ve.Message == "" && len(ve.Errors) == 0) {
			ve.Message = "validation failed"
		}
		return ve
	}
	return &StatusError{Status: code, URL: u}
}

// collectPages walks a paginated collection, bounded by maxPages.
func collectPages[T any](ctx context.Context, c *Client, path string, q url.Values) ([]T, error) {
	var all []T
	for page := 1; page <= maxPages; page++ {
		v := url.Values{}
		for k, vals := range q {
			v[k] = vals
		}
		v.Set("per_page", strconv.Itoa(perPage))
		v.Set("page", strconv.Itoa(page))
		var env struct {
			Data []T   `json:"data"`
			Meta *Meta `json:"meta"`
		}
		if err := c.getJSON(ctx, path, v, &env); err != nil {
			if page > 1 && len(all) > 0 {
				return all, nil // partial results beat none
			}
			return nil, err
		}
		all = append(all, env.Data...)
		if env.Meta == nil || env.Meta.LastPage <= page || len(env.Data) == 0 {
			break
		}
	}
	return all, nil
}

func set(v url.Values, k, val string) {
	if strings.TrimSpace(val) != "" {
		v.Set(k, strings.TrimSpace(val))
	}
}
