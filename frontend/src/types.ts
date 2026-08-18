export interface User {
  id: number
  name: string
  email: string
  roles: string[]
}

export interface MetaRecord {
  id: number
  name: string
  slug: string
  color: string
}

export interface Status extends MetaRecord {
  is_closed: boolean
}

export interface Priority extends MetaRecord {
  level: number
}

export type Category = MetaRecord

export interface CommentAttachment {
  name: string
  url: string
  size: number
}

export interface Comment {
  id: number
  content: string
  user: { id: number; name: string }
  created_at: string
  attachments: CommentAttachment[]
}

export interface Ticket {
  id: number
  ticket_number: string
  subject: string
  description: string
  status: Status
  priority: Priority | null
  category: Category | null
  created_at: string
  updated_at: string
  comments_count: number
  is_closed: boolean
}

export interface TicketDetail extends Ticket {
  comments: Comment[]
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface TicketListResponse {
  data: Ticket[]
  meta: PaginationMeta
}

export interface AuthResponse {
  user: User
  token: string
}

export interface TicketParams {
  status?: string
  priority?: string
  category?: string
  search?: string
  sort?: string
  order?: string
  per_page?: number
  page?: number
}
