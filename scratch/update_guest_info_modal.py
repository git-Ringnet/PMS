import re

path = 'frontend/src/pages/reservation/components/GuestInfoModal.vue'
with open(path, 'r', encoding='utf-8', newline='') as f:
    content = f.read()

# 1. Add import
old_import = "import { normalizeGuestInfoGroups, isCheckedOutPerson, splitCheckedOutPeople } from '@/utils/guest-info'"
new_import = "import { normalizeGuestInfoGroups, isCheckedOutPerson, splitCheckedOutPeople } from '@/utils/guest-info'\r\nimport { maskDateInput } from './room-info-date-utils'"
if new_import not in content:
    assert old_import in content, 'old_import not found'
    content = content.replace(old_import, new_import, 1)

# 2. Add maxlength="10" to date inputs
new_content, count = re.subn(r'(@keydown\.enter\.prevent="\$event\.target\.blur\(\)"\s*\r?\n\s+type="text"\s*\r?\n)(\s*placeholder="dd/mm/yyyy"\s*)',
                            r'\g<1>                                maxlength="10"\r\n\g<2>',
                            content)
print('Replaced date input tags count:', count)
content = new_content

# 3. In handleDateCellInput
old_cell_input = '  if (event?.target) {\r\n    event.target.value = masked\r\n  }'
new_cell_input = '  if (event?.target && event.target.value !== masked) {\r\n    event.target.value = masked\r\n  }'
assert old_cell_input in content, 'old_cell_input not found'
content = content.replace(old_cell_input, new_cell_input, 1)

# 4. In handleDateCellBlur
old_blur = '  if (parsed) {\r\n    targetObj[colKey] = parsed\r\n    delete dateInputBuffers.value[k]\r\n  }'
new_blur = '  if (parsed) {\r\n    targetObj[colKey] = parsed\r\n    delete dateInputBuffers.value[k]\r\n    if (event?.target) {\r\n      event.target.value = formatDateDisplayYY(parsed)\r\n    }\r\n  }'
assert old_blur in content, 'old_blur not found'
content = content.replace(old_blur, new_blur, 1)

# 5. Remove local maskDateInput
pattern = r'function maskDateInput\(val, isDeleting = false\) \{[\s\S]*?\r?\n\}\r?\n'
match = re.search(pattern, content)
assert match is not None, 'local maskDateInput not found'
print('Found local maskDateInput of len:', len(match.group(0)))
content = content[:match.start()] + content[match.end():]

with open(path, 'w', encoding='utf-8', newline='') as f:
    f.write(content)
print('Updated GuestInfoModal.vue successfully!')
