import { usePage } from '@inertiajs/vue3'
import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/**
 * Public URL for a stored image path (photo renditions, featured images, block images).
 * The base comes from the `storageUrl` shared prop: the CDN when images live on R2, /img locally.
 * Absolute URLs and root-relative paths are returned unchanged.
 */
export function imageUrl(path?: string | null): string | undefined {
  if (!path) return undefined
  if (/^(https?:)?\/\//.test(path) || path.startsWith('/') || path.startsWith('data:')) return path
  const base = (usePage().props.storageUrl as string | undefined) ?? '/img'
  return `${base.replace(/\/$/, '')}/${path}`
}
