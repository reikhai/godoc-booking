import { useEffect, useMemo, useState } from 'react'
import { api, ApiError, type Booking, type Doctor, type Slot } from './api'
import './App.css'

const STATUS_COLORS: Record<Booking['status'], string> = {
  pending: '#b7791f',
  confirmed: '#2f855a',
  cancelled: '#c53030',
  completed: '#2b6cb0',
}

function formatSlot(iso: string): string {
  return new Date(iso).toLocaleString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/** Local calendar date key (YYYY-MM-DD) used to group slots by day. */
function dateKey(iso: string): string {
  const d = new Date(iso)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
    d.getDate(),
  ).padStart(2, '0')}`
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  })
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}

export default function App() {
  const [doctors, setDoctors] = useState<Doctor[]>([])
  const [doctorId, setDoctorId] = useState<number | null>(null)
  const [slots, setSlots] = useState<Slot[]>([])
  const [loadingSlots, setLoadingSlots] = useState(false)
  const [selectedDate, setSelectedDate] = useState<string | null>(null)

  const [name, setName] = useState('')
  const [email, setEmail] = useState('')

  const [bookings, setBookings] = useState<Booking[]>([])
  const [message, setMessage] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const selectedDoctor = useMemo(
    () => doctors.find((d) => d.id === doctorId) ?? null,
    [doctors, doctorId],
  )

  useEffect(() => {
    api
      .listDoctors()
      .then((d) => {
        setDoctors(d)
        setDoctorId((prev) => prev ?? d[0]?.id ?? null)
      })
      .catch((e) => setMessage({ kind: 'error', text: e.message }))
  }, [])

  useEffect(() => {
    if (doctorId == null) return
    setLoadingSlots(true)
    setSelectedDate(null) // switching doctor restarts the date → time flow
    api
      .listSlots(doctorId)
      .then(setSlots)
      .catch((e) => setMessage({ kind: 'error', text: e.message }))
      .finally(() => setLoadingSlots(false))
  }, [doctorId])

  async function refreshBookings() {
    if (!email) return
    try {
      setBookings(await api.listBookings(email))
    } catch (e) {
      setMessage({ kind: 'error', text: (e as Error).message })
    }
  }

  async function handleBook(slot: Slot) {
    setMessage(null)
    if (!name.trim() || !email.trim()) {
      setMessage({ kind: 'error', text: 'Enter your name and email before booking.' })
      return
    }
    try {
      const booking = await api.book(slot.id, name.trim(), email.trim())
      setMessage({
        kind: 'success',
        text: `Slot ${formatSlot(slot.start_at)} is reserved for you (booking #${booking.id}, pending). Confirm it below to finalise, or cancel to release it.`,
      })
      if (doctorId != null) setSlots(await api.listSlots(doctorId))
      await refreshBookings()
    } catch (e) {
      const err = e as ApiError
      // 409 = someone else won the slot in a concurrent race.
      setMessage({ kind: 'error', text: err.message })
      if (err.status === 409 && doctorId != null) setSlots(await api.listSlots(doctorId))
    }
  }

  async function handleTransition(b: Booking, action: 'confirm' | 'cancel' | 'complete') {
    setMessage(null)
    try {
      await api.transition(b.id, action)
      await refreshBookings()
      if (doctorId != null) setSlots(await api.listSlots(doctorId))
    } catch (e) {
      setMessage({ kind: 'error', text: (e as Error).message })
    }
  }

  const availableSlots = slots.filter((s) => s.available)

  // Unique bookable dates, in order; times only render once a date is picked.
  const dates = useMemo(() => {
    const seen = new Map<string, string>() // key -> first slot ISO (for the label)
    for (const s of availableSlots) {
      const key = dateKey(s.start_at)
      if (!seen.has(key)) seen.set(key, s.start_at)
    }
    return [...seen.entries()].map(([key, iso]) => ({ key, iso }))
  }, [availableSlots])

  // If the picked date sold out entirely after a refresh, drop the selection.
  const effectiveDate =
    selectedDate && dates.some((d) => d.key === selectedDate) ? selectedDate : null

  const slotsForDate = effectiveDate
    ? availableSlots.filter((s) => dateKey(s.start_at) === effectiveDate)
    : []

  return (
    <div className="app">
      <header>
        <h1>Doc · Consultation Booking</h1>
        <p className="subtitle">Pick a doctor, choose an available slot, and book.</p>
      </header>

      {message && <div className={`banner ${message.kind}`}>{message.text}</div>}

      <section className="patient-card">
        <h2>Your details</h2>
        <div className="field-row">
          <label>
            Name
            <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Jane Tan" />
          </label>
          <label>
            Email
            <input
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              onBlur={refreshBookings}
              placeholder="jane@example.com"
            />
          </label>
        </div>
      </section>

      <section>
        <h2>Doctors</h2>
        <div className="doctor-tabs">
          {doctors.map((d) => (
            <button
              key={d.id}
              className={d.id === doctorId ? 'tab active' : 'tab'}
              onClick={() => setDoctorId(d.id)}
            >
              <strong>{d.name}</strong>
              <span>{d.specialty}</span>
            </button>
          ))}
        </div>
      </section>

      <section>
        <h2>Pick a date{selectedDoctor ? ` · ${selectedDoctor.name}` : ''}</h2>
        {loadingSlots ? (
          <p className="muted">Loading…</p>
        ) : dates.length === 0 ? (
          <p className="muted">No available slots.</p>
        ) : (
          <div className="date-row">
            {dates.map((d) => (
              <button
                key={d.key}
                className={d.key === effectiveDate ? 'date active' : 'date'}
                onClick={() => setSelectedDate(d.key)}
              >
                {formatDate(d.iso)}
              </button>
            ))}
          </div>
        )}
      </section>

      {!loadingSlots && dates.length > 0 && (
        <section>
          <h2>Pick a time</h2>
          {!effectiveDate ? (
            <p className="muted">Select a date above to see available times.</p>
          ) : (
            <div className="slot-grid">
              {slotsForDate.map((s) => (
                <button key={s.id} className="slot" onClick={() => handleBook(s)}>
                  {formatTime(s.start_at)}
                </button>
              ))}
            </div>
          )}
        </section>
      )}

      <section>
        <h2>Your bookings</h2>
        {!email ? (
          <p className="muted">Enter your email above to see your bookings.</p>
        ) : bookings.length === 0 ? (
          <p className="muted">No bookings yet.</p>
        ) : (
          <ul className="booking-list">
            {bookings.map((b) => (
              <li key={b.id} className="booking">
                <div>
                  <span className="badge" style={{ background: STATUS_COLORS[b.status] }}>
                    {b.status}
                  </span>
                  <span className="booking-title">
                    {b.slot ? formatSlot(b.slot.start_at) : `Slot #${b.slot_id}`}
                    {b.slot?.doctor ? ` · ${b.slot.doctor.name}` : ''}
                  </span>
                </div>
                <div className="actions">
                  {b.allowed_transitions.includes('confirmed') && (
                    <button onClick={() => handleTransition(b, 'confirm')}>Confirm</button>
                  )}
                  {b.allowed_transitions.includes('completed') && (
                    <button onClick={() => handleTransition(b, 'complete')}>Complete</button>
                  )}
                  {b.allowed_transitions.includes('cancelled') && (
                    <button className="danger" onClick={() => handleTransition(b, 'cancel')}>
                      Cancel
                    </button>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}
