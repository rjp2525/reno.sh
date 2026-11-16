package api

import (
	"sync"
	"time"
)

type cacheEntry struct {
	data    []byte
	expires time.Time
}

// Cache is a tiny TTL cache of raw response bodies keyed by URL. It is
// bounded by entry count; expired entries are evicted lazily and, when full,
// the entry closest to expiry is dropped.
type Cache struct {
	mu      sync.Mutex
	ttl     time.Duration
	max     int
	entries map[string]cacheEntry
	now     func() time.Time
}

// NewCache creates a cache. ttl <= 0 disables caching.
func NewCache(ttl time.Duration, max int) *Cache {
	if max <= 0 {
		max = 256
	}
	return &Cache{ttl: ttl, max: max, entries: map[string]cacheEntry{}, now: time.Now}
}

// Get returns a fresh entry.
func (c *Cache) Get(key string) ([]byte, bool) {
	if c.ttl <= 0 {
		return nil, false
	}
	c.mu.Lock()
	defer c.mu.Unlock()
	e, ok := c.entries[key]
	if !ok {
		return nil, false
	}
	if c.now().After(e.expires) {
		delete(c.entries, key)
		return nil, false
	}
	return e.data, true
}

// Set stores an entry.
func (c *Cache) Set(key string, data []byte) {
	if c.ttl <= 0 {
		return
	}
	c.mu.Lock()
	defer c.mu.Unlock()
	now := c.now()
	if _, exists := c.entries[key]; !exists && len(c.entries) >= c.max {
		var oldestKey string
		var oldest time.Time
		for k, e := range c.entries {
			if now.After(e.expires) {
				delete(c.entries, k)
				continue
			}
			if oldestKey == "" || e.expires.Before(oldest) {
				oldestKey, oldest = k, e.expires
			}
		}
		if len(c.entries) >= c.max && oldestKey != "" {
			delete(c.entries, oldestKey)
		}
	}
	c.entries[key] = cacheEntry{data: data, expires: now.Add(c.ttl)}
}

// Len returns the number of entries (including stale ones not yet evicted).
func (c *Cache) Len() int {
	c.mu.Lock()
	defer c.mu.Unlock()
	return len(c.entries)
}
