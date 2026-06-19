import os

file_path = r"c:\Users\Nguyen Tho Thang\OneDrive\Desktop\PMS\PMS\frontend\src\pages\housekeeping\components\LostAndFound.vue"

with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

replacements = {
    "item.category": "item.item_found",
    "item.location": "item.where_found",
    "item.finder": "item.who_found",
    "item.keeper": "item.received",
    "item.handledDate": "item.date_handling",
    "item.handledTime": "item.time_handling",
    "item.method": "item.method_handling",
    "item.returner": "item.delieved_handling",
    "item.receiver": "item.received_handling",
    "item.foundDate": "item.date_found",
    "item.foundTime": "item.time_found",
    "item.remarks": "item.remarks",
    "formState.category": "formState.item_found",
    "formState.location": "formState.where_found",
    "formState.finder": "formState.who_found",
    "formState.keeper": "formState.received",
    "formState.handledDate": "formState.date_handling",
    "formState.handledTime": "formState.time_handling",
    "formState.method": "formState.method_handling",
    "formState.returner": "formState.delieved_handling",
    "formState.receiver": "formState.received_handling",
    "formState.foundDate": "formState.date_found",
    "formState.foundTime": "formState.time_found",
    "formState.remarks": "formState.remarks",
    "category: '',": "item_found: '',",
    "foundTime: currentTime,": "time_found: currentTime,",
    "foundTime: '',": "time_found: '',",
    "foundDate: currentDate,": "date_found: currentDate,",
    "foundDate: '',": "date_found: '',",
    "location: '',": "where_found: '',",
    "finder: '',": "who_found: '',",
    "keeper: '',": "received: '',",
    "handledDate: '',": "date_handling: '',",
    "handledTime: '',": "time_handling: '',",
    "method: 'Lưu kho',": "method_handling: 'Lưu kho',",
    "method: '',": "method_handling: '',",
    "returner: '',": "delieved_handling: '',",
    "receiver: '',": "received_handling: '',",
}

for old, new in replacements.items():
    content = content.replace(old, new)

with open(file_path, "w", encoding="utf-8") as f:
    f.write(content)

print("Done")
