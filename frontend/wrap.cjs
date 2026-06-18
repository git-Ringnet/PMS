const fs = require('fs');

let content = fs.readFileSync('src/pages/housekeeping/components/PrintTasksTab.vue', 'utf8');

// The replacement is:
// Find `<div v-if="activeFilterMenu === '...'"`
// Wrap it in `<Transition name="dropdown-fade">\n ... \n</Transition>`
// We can do this by matching the `v-if` div opening tag, and then the end of the div before `</th>`

content = content.replace(/(<div v-if="activeFilterMenu === '([^']+)'"[^>]*>[\s\S]*?)(<\/th>)/g, function(match, p1, p2, p3) {
  // Check if it's already wrapped
  if (content.substring(Math.max(0, match.index - 50), match.index).includes('Transition')) {
    return match;
  }
  return '<Transition name="dropdown-fade">\n              ' + p1.trimRight() + '\n              </Transition>\n            ' + p3;
});

if (!content.includes('<style scoped>')) {
  content += '\n<style scoped>\n.dropdown-fade-enter-active,\n.dropdown-fade-leave-active {\n  transition: opacity 0.15s ease, transform 0.15s ease;\n}\n.dropdown-fade-enter-from,\n.dropdown-fade-leave-to {\n  opacity: 0;\n  transform: translateY(-5px);\n}\n</style>\n';
}

fs.writeFileSync('src/pages/housekeeping/components/PrintTasksTab.vue', content);
console.log('Done correctly');
