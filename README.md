# Event Registration Management System
### Workflow Documentation
---

## 📌 System Overview
A web-based Event Registration Management System built with **PHP + MySQL + XAMPP** that allows students to browse and register for institutional events in the easiest way possible.

---

## 🔄 Workflow

### 1. Homepage
- Display upcoming/available events
- Show event cards with: name, date, venue, and available slots
- Navigation bar with links to:
  - Browse Events
  - Login / Register

---

### 2. User Registration & Login
- Student fills out registration form:
  - Full Name
  - Student ID
  - Email
  - Password *(hashed before storing)*
- After registering, student is redirected to the **Login Page**
- Upon successful login, a **PHP session** is created
- Redirects to the **Student Dashboard**

---

### 3. Event Browsing Page
- Students can view all available events
- Filter events by:
  - Category
  - Date
- Each event card displays:
  - Title
  - Description
  - Date & Time
  - Venue
  - Remaining Slots
- Click **"View Details"** to see full event info

---

### 4. Event Registration Flow

```
Student clicks "Register for Event"
        ↓
Is the student logged in?
   ├── NO  → Redirect to Login Page
   └── YES ↓
Are slots still available?
   ├── NO  → Show "Event Full" message
   └── YES ↓
Has the student already registered?
   ├── YES → Show "Already Registered" message
   └── NO  ↓
Save registration to database
        ↓
Show "Registration Successful!" confirmation
```

---

### 5. Student Dashboard
After logging in, students can:
- View all events they have registered for
- See registration status:
  - ✅ Confirmed
  - ⏳ Pending
  - ❌ Cancelled
- Cancel a registration if needed

---

### 6. Admin Panel *(Enhancement — +5 Creativity Points)*
A restricted page accessible only to admins:
- Add, edit, or delete events
- Set maximum slot limits per event
- View list of registered students per event
- Export registration data (optional)

---

## 🗄️ Database Structure

### `users` table
| Field       | Type         | Description          |
|-------------|--------------|----------------------|
| user_id     | INT (PK, AI) | Unique user ID       |
| full_name   | VARCHAR      | Student's full name  |
| student_id  | VARCHAR      | Institutional ID     |
| email       | VARCHAR      | Email address        |
| password    | VARCHAR      | Hashed password      |
| role        | ENUM         | 'student' or 'admin' |
| created_at  | TIMESTAMP    | Registration date    |

### `events` table
| Field       | Type         | Description          |
|-------------|--------------|----------------------|
| event_id    | INT (PK, AI) | Unique event ID      |
| title       | VARCHAR      | Event name           |
| description | TEXT         | Event details        |
| date_time   | DATETIME     | Schedule             |
| venue       | VARCHAR      | Location             |
| max_slots   | INT          | Maximum capacity     |
| created_at  | TIMESTAMP    | Date event was added |

### `registrations` table
| Field           | Type         | Description               |
|-----------------|--------------|---------------------------|
| registration_id | INT (PK, AI) | Unique registration ID    |
| user_id         | INT (FK)     | References `users`        |
| event_id        | INT (FK)     | References `events`       |
| status          | ENUM         | confirmed/pending/cancelled |
| registered_at   | TIMESTAMP    | Date of registration      |

---

## 👥 Suggested Group Roles

| Role                  | Responsibility                                      |
|-----------------------|-----------------------------------------------------|
| Frontend Developer(s) | HTML/CSS for all pages, responsive layout           |
| Backend Developer     | PHP logic, form processing, session management      |
| Database Manager      | Schema design, SQL queries, data integrity          |
| Admin Panel Dev       | Admin features, slot management, student list view  |
| Tester & Documenter   | Testing all features, preparing presentation slides |

---

## 📋 Presentation Checklist

- [ ] Introduce the system and its purpose
- [ ] Explain each member's role
- [ ] Walk through the Homepage
- [ ] Demo the Registration & Login process
- [ ] Show a live Event Registration
- [ ] Display stored data in the database (phpMyAdmin)
- [ ] Explain how the code works (briefly)
- [ ] Answer questions from the panel

---

## 🏁 Grading Alignment

| Rubric Criteria          | How This System Covers It                         |
|--------------------------|---------------------------------------------------|
| System Functionality     | Full registration flow with validation            |
| Presentation & Walkthrough | Clear page-by-page demo plan                   |
| Code Understanding       | Documented logic for each component              |
| User Interface           | Clean event cards, dashboard, forms              |
| Database Implementation  | 3-table relational structure                     |
| Team Participation       | Defined roles for each member                    |
| Creativity / Enhancements| Admin panel, slot limits, status tracking        |

---

*Prepared for Midterm Group Project — Web Registration System*
