import { ref, onBeforeUnmount } from 'vue'

/**
 * Kéo thả modal bằng thanh tiêu đề (cùng cách với QuickUpdateModal).
 * Dùng: style="transform: translate(x px, y px)" cho khung modal, @mousedown="startDrag" cho thanh tiêu đề.
 */
export function useDraggableModal() {
  const position = ref({ x: 0, y: 0 })
  const isDragging = ref(false)
  let dragStart = { x: 0, y: 0 }
  let rafId = null

  function onMove(event) {
    if (!isDragging.value || rafId) return
    rafId = requestAnimationFrame(() => {
      position.value = { x: event.clientX - dragStart.x, y: event.clientY - dragStart.y }
      rafId = null
    })
  }

  function stopDrag() {
    isDragging.value = false
    if (rafId) {
      cancelAnimationFrame(rafId)
      rafId = null
    }
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', stopDrag)
  }

  function startDrag(event) {
    if (event.target.closest('button, input, select, textarea, a, label')) return
    isDragging.value = true
    dragStart = { x: event.clientX - position.value.x, y: event.clientY - position.value.y }
    document.addEventListener('mousemove', onMove)
    document.addEventListener('mouseup', stopDrag)
  }

  function resetPosition() {
    position.value = { x: 0, y: 0 }
  }

  onBeforeUnmount(stopDrag)

  return { position, isDragging, startDrag, resetPosition }
}
