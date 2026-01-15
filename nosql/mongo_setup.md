# MongoDB (NoSQL) — Collections

## reviews
{
  _id: ObjectId,
  trip_id: Number,
  driver_id: Number,
  rider_id: Number,
  rating: Number,       // 1..5
  comment: String,
  status: 'pending'|'approved'|'rejected',
  created_at: ISODate
}

## incidents
{
  _id: ObjectId,
  trip_id: Number,
  driver_id: Number,
  rider_id: Number,
  note: String,
  status: 'open'|'contacted'|'resolved',
  created_at: ISODate
}

## audit_logs
{
  _id: ObjectId,
  actor_id: Number,
  action: String,
  meta: Object,
  at: ISODate
}
