// Theme settings for text style and color unification
// Users requested white/black text only (no grey/slate/etc.) and no bolding (normal font weight).

export const TEXT_THEME = {
  // Pure black text in light mode, pure white text in dark mode, normal font weight, no bold.
  // Standard text for labels, table headers, table body cells, stats cards, and menu lists.
  base: 'text-black dark:text-white font-normal',
  
  // Table headers (pure black in light mode, pure white in dark mode, normal font weight, no bold)
  tableHeader: 'text-black dark:text-white font-normal',
  
  // Table cells
  tableCell: 'text-black dark:text-white font-normal',
  
  // Stats labels (e.g. "ĐÃ ĐẾN", "ĐÃ ĐI")
  statsLabel: 'text-black dark:text-white font-normal',
  
  // Stats values (e.g. "12/23")
  statsValue: 'text-black dark:text-white font-normal',

  // Dropdown or context menu item
  menuItem: 'text-black dark:text-white font-normal',
  
  // Menu header/title
  menuTitle: 'text-black dark:text-white font-normal',
  
  // Sidebar/Filter panel labels
  sidebarLabel: 'text-black dark:text-white font-normal',
}
