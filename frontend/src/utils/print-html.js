// In một tài liệu HTML qua iframe ẩn để không bị chặn popup và không in kèm giao diện phía sau.
// document.open() xóa mọi handler đã gán trên iframe (kể cả onload), nên ghi nội dung trước
// rồi tự chờ logo/ảnh tải xong (tối đa 2 giây) mới mở hộp thoại in.
export function printHtmlDocument(html) {
  const frame = document.createElement('iframe')
  frame.style.cssText = 'position:fixed;right:0;bottom:0;width:1px;height:1px;border:0;opacity:0;pointer-events:none;'
  document.body.appendChild(frame)
  const frameWindow = frame.contentWindow
  const frameDocument = frameWindow.document
  frameDocument.open()
  frameDocument.write(html)
  frameDocument.close()

  const pendingImages = Array.from(frameDocument.images || []).filter(image => !image.complete)
  const imagesReady = Promise.all(pendingImages.map(image => new Promise(resolve => {
    image.addEventListener('load', resolve, { once: true })
    image.addEventListener('error', resolve, { once: true })
  })))
  const timeout = new Promise(resolve => setTimeout(resolve, 2000))

  return Promise.race([imagesReady, timeout]).then(() => {
    frameWindow.focus()
    frameWindow.print()
    setTimeout(() => frame.remove(), 1000)
  })
}

// Bọc một đoạn HTML (đã render, đã dịch trên màn hình) thành tài liệu in độc lập.
export function wrapPrintDocument(bodyHtml, title = '') {
  return `<!doctype html><html><head><meta charset="utf-8"><title>${title}</title></head><body style="margin:0">${bodyHtml}</body></html>`
}
