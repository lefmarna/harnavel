# Screen Transitions

## Page Map

### Public

| Route | Page    | Layout  |
| ----- | ------- | ------- |
| `/`   | Welcome | Standalone |

### Auth

| Route                  | Page                 | Layout |
| ---------------------- | -------------------- | ------ |
| `/login`               | Login                | Auth   |
| `/register`            | Register             | Auth   |
| `/forgot-password`     | Forgot Password      | Auth   |
| `/reset-password`      | Reset Password       | Auth   |
| `/confirm-password`    | Confirm Password     | Auth   |
| `/verify-email`        | Email Verification   | Auth   |
| `/two-factor-challenge`| Two-Factor Challenge | Auth   |

### Protected (auth + verified)

| Route                  | Page       | Layout  |
| ---------------------- | ---------- | ------- |
| `/dashboard`           | Dashboard  | Sidebar |
| `/settings`            | → redirect to `/settings/profile` | |
| `/settings/profile`    | Profile    | Sidebar + Settings Nav |
| `/settings/password`   | Password   | Sidebar + Settings Nav |
| `/settings/two-factor` | Two-Factor | Sidebar + Settings Nav |
| `/settings/appearance` | Appearance | Sidebar + Settings Nav |

## Navigation Structure

```
Desktop (≥1024px)           Mobile (<1024px)
┌──────────┬──────────┐     ┌──────────────────┐
│ Sidebar  │  Main    │     │ Header [≡]       │
│ (256px)  │ Content  │     ├──────────────────┤
│          │          │     │ Main Content     │
│ - Dash   │          │     │                  │
│          │          │     │ [≡] → Sheet Nav  │
│ [footer] │          │     │                  │
│ - Repo   │          │     │                  │
│ - Docs   │          │     │                  │
└──────────┴──────────┘     └──────────────────┘
```

## Layout Switching

**Inertia Layouts**:
- **AuthLayout**: `/login`, `/register`, `/forgot-password`, etc. Centered card, no sidebar.
- **AppLayout**: Dashboard, Settings. Includes Sidebar + Header + UserMenu.
- **Settings Layout**: Nested inside AppLayout with its own sub-navigation (Profile, Password, Two-Factor, Appearance).

**Middleware**:
- `auth` + `verified`: Protects Dashboard/Settings.
- `guest`: Protects Login/Register (redirects to Dashboard if logged in).

## Verification Checklist

- [ ] **Completeness**: All planned screens defined.
- [ ] **Flow**: No dead ends in user journey.
- [ ] **Guards**: Protected routes use `auth` + `verified` middleware.
- [ ] **Mobile**: Navigation accessible on small screens.
