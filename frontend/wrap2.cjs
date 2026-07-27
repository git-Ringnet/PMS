const fs = require('fs');

let content = fs.readFileSync('src/pages/housekeeping/components/PrintTasksTab.vue', 'utf8');

const menus = ['ttdk', 'ttphong', 'tang', 'loaiphong', 'dangphong', 'nhanphongtre', 'themgiuong', 'tenkhach', 'madk', 'congty'];

for (const menu of menus) {
  const searchStr = `<div v-if="activeFilterMenu === '${menu}'"`;
  if (content.includes(searchStr) && !content.includes(`Transition name="dropdown-fade">\n              ${searchStr}`)) {
    content = content.replace(searchStr, `<Transition name="dropdown-fade">\n              ${searchStr}`);
    // Now we need to find the closing tag.
    // The closing tag is before the `</th>` that follows this menu.
    // We can do this with a regex:
    const regex = new RegExp(`(<Transition name="dropdown-fade">\\s*<div v-if="activeFilterMenu === '${menu}'"[\\s\\S]*?)(</th>)`, 'g');
    content = content.replace(regex, `$1</Transition>\n            $2`);
  }
}

if (!content.includes('<style scoped>')) {
  content += '\n<style scoped>\n.dropdown-fade-enter-active,\n.dropdown-fade-leave-active {\n  transition: opacity 0.15s ease, transform 0.15s ease;\n}\n.dropdown-fade-enter-from,\n.dropdown-fade-leave-to {\n  opacity: 0;\n  transform: translateY(-5px);\n}\n</style>\n';
}

fs.writeFileSync('src/pages/housekeeping/components/PrintTasksTab.vue', content);
console.log('Fixed wrapper logic');
