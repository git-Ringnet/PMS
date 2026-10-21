// Id of this browser tab. Every object posted on a BroadcastChannel carries it
// as `originTabId`, so a page can tell another tab's update from one made by a
// modal in the same tab (a BroadcastChannel also delivers to other channel
// objects of the same tab).
export const TAB_ID = `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`

export function withOrigin(message, tabId = TAB_ID) {
  if (!message || typeof message !== 'object' || Array.isArray(message) || 'originTabId' in message) {
    return message
  }
  return { ...message, originTabId: tabId }
}

export function isFromOtherTab(message, tabId = TAB_ID) {
  return !!message && typeof message === 'object' && !!message.originTabId && message.originTabId !== tabId
}

export function installBroadcastOrigin(Channel = globalThis.BroadcastChannel) {
  if (!Channel || Channel.prototype.__pmsOriginInstalled) return
  const postMessage = Channel.prototype.postMessage
  Channel.prototype.postMessage = function (message) {
    return postMessage.call(this, withOrigin(message))
  }
  Channel.prototype.__pmsOriginInstalled = true
}
