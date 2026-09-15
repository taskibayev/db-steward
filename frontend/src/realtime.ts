import { Centrifuge, type PublicationContext } from 'centrifuge'
import { getRealtimeToken } from './api'

let client: Centrifuge | null = null
const listeners = new Set<(data: unknown) => void>()

export function startRealtime(listener: (data: unknown) => void): () => void {
  listeners.add(listener)
  if (!client) {
    const protocol = globalThis.location.protocol === 'https:' ? 'wss:' : 'ws:'
    client = new Centrifuge(`${protocol}//${globalThis.location.host}/connection/websocket`, {
      getToken: getRealtimeToken,
    })
    client.on('publication', (context: PublicationContext) => {
      for (const notify of listeners) notify(context.data)
    })
    client.connect()
  }

  return () => {
    listeners.delete(listener)
    if (!listeners.size && client) {
      client.disconnect()
      client = null
    }
  }
}
