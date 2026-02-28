# PolyLife API Docs (v1)

Dokumentasi endpoint API untuk integrasi mobile (Flutter) dengan Laravel Sanctum.

## Base URL

- Local: `http://127.0.0.1:8000/api/v1`

## Authentication

- Skema auth: **Bearer Token (Sanctum Personal Access Token)**
- Header untuk endpoint protected:

```http
Authorization: Bearer <access_token>
Accept: application/json
Content-Type: application/json
```

## Response Pattern

- Success list biasanya berisi: `data`, `meta`, `links`
- Success detail biasanya berisi: `data`
- Success action biasanya berisi: `message`
- Error umumnya berisi: `message`
- Validation error (`422`) berisi: `message` + `errors`

## Error Status Umum

- `401` token tidak valid/tidak ada
- `403` akun diblokir / akses ditolak
- `404` data tidak ditemukan
- `422` validasi gagal
- `429` rate limit terlampaui

## Access Rules

- Group protected memakai middleware: `auth:sanctum` + `api-active`
- Jika akun sudah banned/non-active, request akan ditolak (`403`)
- API mobile **hanya untuk role `user`** (`admin` dan `super_admin` ditolak `403`)
- `POST /auth/login` dibatasi `throttle:10,1` (maks 10 request per menit)

---

## 1) Auth

### POST `/auth/login`

Login dan membuat token Sanctum baru.

**Body**

```json
{
  "email": "1@2.c",
  "password": "1",
  "device_name": "flutter-android"
}
```

**Response 200**

```json
{
  "message": "Login berhasil.",
  "data": {
    "token_type": "Bearer",
    "access_token": "plain_text_token",
    "user": {
      "id": 1,
      "name": "User",
      "email": "1@2.c",
      "role": "user",
      "role_label": "Pengguna",
      "admin_level": 0,
      "account_status": "active",
      "email_verified_at": null,
      "affiliation": {
        "type": "university",
        "name": "Universitas Indonesia",
        "student_id_type": "nim",
        "student_id_number": "2400123456",
        "status": "pending"
      }
    }
  }
}
```

---

### GET `/auth/me` (protected)

Ambil profil user yang sedang login.

**Response 200**

```json
{
  "data": {
    "id": 1,
    "name": "User",
    "email": "1@2.c",
    "role": "user",
    "role_label": "Pengguna",
    "admin_level": 0,
    "account_status": "active",
    "email_verified_at": null,
    "affiliation": {
      "type": "university",
      "name": "Universitas Indonesia",
      "student_id_type": "nim",
      "student_id_number": "2400123456",
      "status": "pending"
    }
  }
}
```

---

### POST `/auth/logout` (protected)

Hapus token aktif saat ini.

**Response 200**

```json
{
  "message": "Logout berhasil."
}
```

---

### POST `/auth/logout-all` (protected)

Hapus semua token user.

**Response 200**

```json
{
  "message": "Semua sesi berhasil diakhiri."
}
```

---

## 2) Catatan (protected)

### GET `/catatan`

List catatan aktif (bukan sampah), milik user login.

**Query (opsional)**

- `per_page` (default: `20`, max: `100`)

**Response 200**

```json
{
  "data": [
    {
      "id": 10,
      "judul": "Materi Web",
      "isi": "Isi catatan...",
      "tanggal": "2026-02-27",
      "status_sampah": false,
      "created_at": "2026-02-27T08:00:00+00:00",
      "updated_at": "2026-02-27T08:00:00+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1,
    "trash_count": 0
  },
  "links": {
    "next": null,
    "prev": null
  }
}
```

---

### GET `/catatan/trash`

List catatan yang ada di sampah.

**Query (opsional)**

- `per_page` (default: `20`, max: `100`)

---

### POST `/catatan`

Buat catatan baru.

**Body**

```json
{
  "judul": "Judul Catatan",
  "isi": "Isi catatan",
  "tanggal": "2026-02-27"
}
```

**Response 201**

```json
{
  "message": "Catatan berhasil ditambahkan.",
  "data": {
    "id": 11,
    "judul": "Judul Catatan",
    "isi": "Isi catatan",
    "tanggal": "2026-02-27",
    "status_sampah": false,
    "created_at": "2026-02-27T09:00:00+00:00",
    "updated_at": "2026-02-27T09:00:00+00:00"
  }
}
```

---

### GET `/catatan/{id}`

Ambil detail 1 catatan milik user login.

---

### PUT/PATCH `/catatan/{id}`

Update catatan milik user login.

**Body**

```json
{
  "judul": "Judul Baru",
  "isi": "Isi baru",
  "tanggal": "2026-02-28"
}
```

**Response 200**

```json
{
  "message": "Catatan berhasil diperbarui.",
  "data": {
    "id": 11,
    "judul": "Judul Baru",
    "isi": "Isi baru",
    "tanggal": "2026-02-28",
    "status_sampah": false,
    "created_at": "2026-02-27T09:00:00+00:00",
    "updated_at": "2026-02-27T09:10:00+00:00"
  }
}
```

---

### DELETE `/catatan/{id}`

Soft delete: pindah ke sampah (`status_sampah = true`).

**Response 200**

```json
{
  "message": "Catatan dipindahkan ke sampah."
}
```

---

### PATCH `/catatan/{id}/restore`

Pulihkan catatan dari sampah.

**Response 200**

```json
{
  "message": "Catatan berhasil dipulihkan.",
  "data": {
    "id": 11,
    "judul": "Judul Baru",
    "isi": "Isi baru",
    "tanggal": "2026-02-28",
    "status_sampah": false,
    "created_at": "2026-02-27T09:00:00+00:00",
    "updated_at": "2026-02-27T09:12:00+00:00"
  }
}
```

---

### DELETE `/catatan/{id}/force-delete`

Hapus permanen catatan.

**Response 200**

```json
{
  "message": "Catatan dihapus permanen."
}
```

---

## 3) Pengumuman (protected)

### GET `/pengumuman`

List pengumuman yang visible untuk user login (sesuai afiliasi/global).

**Query (opsional)**

- `q` (search by title/body/creator name)
- `per_page` (default: `12`, max: `50`)

**Response 200**

```json
{
  "data": [
    {
      "id": 1,
      "title": "Agenda Kampus",
      "body": "Isi lengkap pengumuman...",
      "image_url": "/storage/broadcasts/abc.webp",
      "status": "published",
      "target_mode": "affiliation",
      "published_at": "2026-02-27T02:23:00+00:00",
      "creator": {
        "id": 2,
        "name": "Admin Kampus"
      },
      "targets": [
        {
          "affiliation_type": "university",
          "affiliation_name": "UNDIP"
        }
      ],
      "excerpt": "Isi lengkap pengumuman..."
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 12,
    "total": 1
  },
  "links": {
    "next": null,
    "prev": null
  }
}
```

---

### GET `/pengumuman/{id}`

Detail pengumuman tunggal yang visible untuk user login.

**Response 200**

```json
{
  "data": {
    "id": 1,
    "title": "Agenda Kampus",
    "body": "Isi lengkap pengumuman...",
    "image_url": "/storage/broadcasts/abc.webp",
    "status": "published",
    "target_mode": "affiliation",
    "published_at": "2026-02-27T02:23:00+00:00",
    "creator": {
      "id": 2,
      "name": "Admin Kampus"
    },
    "targets": [
      {
        "affiliation_type": "university",
        "affiliation_name": "UNDIP"
      }
    ]
  }
}
```

---

## Quick Test with cURL

### Login

```bash
curl -X POST "http://127.0.0.1:8000/api/v1/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"1@2.c","password":"1","device_name":"flutter-android"}'
```

### Fetch Catatan

```bash
curl "http://127.0.0.1:8000/api/v1/catatan" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <access_token>"
```

### Fetch Pengumuman

```bash
curl "http://127.0.0.1:8000/api/v1/pengumuman?per_page=12" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <access_token>"
```
