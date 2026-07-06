// Thin typed wrapper around the GoDoc booking API.

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api'

export type Doctor = {
  id: number
  name: string
  specialty: string | null
}

export type Slot = {
  id: number
  doctor_id: number
  start_at: string
  end_at: string
  available: boolean
}

export type BookingStatus = 'pending' | 'confirmed' | 'cancelled' | 'completed'

export type Booking = {
  id: number
  status: BookingStatus
  allowed_transitions: BookingStatus[]
  slot_id: number
  patient_id: number
  confirmed_at: string | null
  cancelled_at: string | null
  completed_at: string | null
  created_at: string | null
  slot?: Slot & { doctor?: Doctor }
  patient?: { id: number; name: string; email: string }
}

/** An API error that carries the HTTP status so callers can special-case 409/422. */
export class ApiError extends Error {
  status: number
  payload?: unknown

  constructor(status: number, message: string, payload?: unknown) {
    super(message)
    this.status = status
    this.payload = payload
  }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${BASE_URL}${path}`, {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    ...init,
  })

  const body = res.status === 204 ? null : await res.json().catch(() => null)

  if (!res.ok) {
    const message =
      (body && typeof body === 'object' && 'message' in body && (body as any).message) ||
      `Request failed (${res.status})`
    throw new ApiError(res.status, message, body)
  }

  return body as T
}

type Wrapped<T> = { data: T }

export const api = {
  listDoctors: () => request<Wrapped<Doctor[]>>('/doctors').then((r) => r.data),

  listSlots: (doctorId: number) =>
    request<Wrapped<Slot[]>>(`/doctors/${doctorId}/slots`).then((r) => r.data),

  book: (slotId: number, name: string, email: string) =>
    request<Wrapped<Booking>>('/bookings', {
      method: 'POST',
      body: JSON.stringify({ slot_id: slotId, patient: { name, email } }),
    }).then((r) => r.data),

  listBookings: (email: string) =>
    request<Wrapped<Booking[]>>(`/bookings?email=${encodeURIComponent(email)}`).then(
      (r) => r.data,
    ),

  transition: (bookingId: number, action: 'confirm' | 'cancel' | 'complete') =>
    request<Wrapped<Booking>>(`/bookings/${bookingId}/${action}`, { method: 'POST' }).then(
      (r) => r.data,
    ),
}
