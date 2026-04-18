export interface NavItem {
  title: string
  slug: string
}

export interface NavSection {
  title: string
  items: NavItem[]
}

export interface TocEntry {
  id: string
  text: string
  level: number
}

export interface SearchEntry {
  slug: string
  title: string
  section?: string
  sectionId?: string
  body: string
}

export interface DocsPageMeta {
  slug: string
  title: string
  toc: TocEntry[]
  html: string
  prev?: NavItem
  next?: NavItem
}
