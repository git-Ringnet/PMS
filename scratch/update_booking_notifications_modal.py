path = 'frontend/src/pages/reservation/components/BookingNotificationsModal.vue'
with open(path, 'r', encoding='utf-8', newline='') as f:
    content = f.read()

# 1. Update SingleDatePicker input-class to full yellow background and border
old_date_class = 'input-class="!h-[32px] !py-0 !px-2.5 !rounded-lg !border-slate-200 !text-xs !font-normal"'
new_date_class = 'input-class="!h-[32px] !py-0 !px-2.5 !rounded-lg !border-[#F1DD8A] !bg-[#FFF8DB] !text-xs !font-normal focus-within:!border-amber-400 focus-within:!ring-1 focus-within:!ring-amber-400"'

assert content.count(old_date_class) == 2, f"Expected 2 occurrences of old_date_class, found {content.count(old_date_class)}"
content = content.replace(old_date_class, new_date_class)

# 2. Update footer: remove Close button, move Add button to the far right, align justify-end
old_footer = '''      <!-- MODAL FOOTER -->
      <footer class="flex flex-wrap justify-center gap-2.5 border-t border-slate-100 bg-slate-50 px-5 py-3 shrink-0 rounded-b-xl">
        <button 
          type="button" 
          class="btn-pms-primary" 
          :disabled="saving" 
          @click="addNotification"
        >
          <i class="fa-solid fa-plus"></i>
          <span>Thêm</span>
        </button>
        <button 
          type="button" 
          class="btn-pms-secondary" 
          :disabled="!selectedId || saving" 
          @click="editSelected"
        >
          <i class="fa-solid fa-pen"></i>
          <span>Sửa</span>
        </button>
        <button 
          type="button" 
          class="btn-pms-primary" 
          :disabled="!editing || saving" 
          @click="saveEdited"
        >
          <i class="fa-solid fa-floppy-disk"></i>
          <span>Lưu</span>
        </button>
        <button 
          type="button" 
          class="btn-pms-danger" 
          :disabled="!selectedId || saving" 
          @click="remove"
        >
          <i class="fa-solid fa-trash"></i>
          <span>Xóa</span>
        </button>
        <button 
          type="button" 
          class="btn-pms-close" 
          @click="close"
        >
          <i class="fa-solid fa-xmark"></i>
          <span>Đóng</span>
        </button>
      </footer>'''.replace('\n', '\r\n')

new_footer = '''      <!-- MODAL FOOTER -->
      <footer class="flex flex-wrap items-center justify-end gap-2.5 border-t border-slate-100 bg-slate-50 px-5 py-3 shrink-0 rounded-b-xl">
        <button 
          type="button" 
          class="btn-pms-secondary" 
          :disabled="!selectedId || saving" 
          @click="editSelected"
        >
          <i class="fa-solid fa-pen"></i>
          <span>Sửa</span>
        </button>
        <button 
          type="button" 
          class="btn-pms-primary" 
          :disabled="!editing || saving" 
          @click="saveEdited"
        >
          <i class="fa-solid fa-floppy-disk"></i>
          <span>Lưu</span>
        </button>
        <button 
          type="button" 
          class="btn-pms-danger" 
          :disabled="!selectedId || saving" 
          @click="remove"
        >
          <i class="fa-solid fa-trash"></i>
          <span>Xóa</span>
        </button>
        <button 
          type="button" 
          class="btn-pms-primary" 
          :disabled="saving" 
          @click="addNotification"
        >
          <i class="fa-solid fa-plus"></i>
          <span>Thêm</span>
        </button>
      </footer>'''.replace('\n', '\r\n')

assert old_footer in content, "old_footer not found"
content = content.replace(old_footer, new_footer, 1)

with open(path, 'w', encoding='utf-8', newline='') as f:
    f.write(content)

print("Updated BookingNotificationsModal.vue successfully!")

