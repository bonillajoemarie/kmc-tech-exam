import { useCallback, useEffect, useState } from 'react'
import { api } from './api'
import type { Category, Priority, Status } from '../types'

interface Meta {
  statuses: Status[]
  priorities: Priority[]
  categories: Category[]
}

let cache: Meta | null = null

async function fetchMeta(): Promise<Meta> {
  if (cache) return cache
  const [statuses, priorities, categories] = await Promise.all([
    api<Status[]>('/meta/statuses'),
    api<Priority[]>('/meta/priorities'),
    api<Category[]>('/meta/categories'),
  ])
  cache = { statuses, priorities, categories }
  return cache
}

export function useMeta(): { meta: Meta | null; reload: () => void } {
  const [meta, setMeta] = useState<Meta | null>(cache)

  const reload = useCallback(() => {
    cache = null
    setMeta(null)
    fetchMeta().then(setMeta)
  }, [])

  useEffect(() => {
    let active = true
    fetchMeta().then((m) => {
      if (active) setMeta(m)
    })
    return () => {
      active = false
    }
  }, [])

  return { meta, reload }
}