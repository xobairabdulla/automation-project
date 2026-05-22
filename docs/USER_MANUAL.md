# User Manual — Facebook AI Automation SaaS

Role-based guide for all user types.

---

## Table of Contents

1. [Roles Overview](#roles-overview)
2. [Super Admin](#super-admin)
3. [Client Admin](#client-admin)
4. [Agent](#agent)
5. [Support Admin](#support-admin)
6. [Common Features](#common-features)

---

## Roles Overview

| Role | Access |
|------|--------|
| **Super Admin** | Full platform control — users, plans, payments, logs, settings |
| **Client Admin** | Their own workspace — Facebook pages, automation, billing, team |
| **Agent** | Inbox only — read conversations, send replies, human takeover |
| **Support Admin** | Inbox read-only — view conversations, cannot reply |

Default credentials (change before production):
- Super Admin: `admin@demo.com` / `password`
- Demo Client: `client@demo.com` / `password`

---

## Super Admin

Login with the super-admin account. The admin panel is at `/admin/dashboard`.

### Admin Dashboard

Shows platform-wide statistics:
- Total users, active subscriptions, total revenue, monthly revenue
- Recent signups, recent payments

### User Management (`/admin/users`)

**View users:** Search by name or email. Filter by status (active/suspended) or plan.

**Suspend a user:**
1. Click the user's name to open their profile.
2. Click **Suspend**. The user cannot log in until reactivated.

**Activate a suspended user:**
1. Open user profile.
2. Click **Activate**.

**Assign a plan manually (admin grant):**
1. Open user profile → **Assign Plan**.
2. Select plan and billing cycle → Confirm.
3. A `completed` payment record is created with `type = admin_granted` (no charge).

**Extend usage limits:**
1. Open user profile → **Extend Limit**.
2. Enter extra message/comment/AI reply counts → Confirm.
3. Limits add on top of the current subscription limits immediately.

### Plan Management (`/admin/plans`)

**Create a plan:**
1. Click **New Plan**.
2. Fill: name, monthly price, yearly price, limits (messages, comments, AI replies, pages, team members, knowledge base items).
3. For Stripe: enter the `stripe_monthly_price_id` and `stripe_yearly_price_id` from your Stripe dashboard.
4. Click **Save**.

**Edit a plan:** Click the plan name → edit fields → Save.

**Deactivate a plan:** Deactivated plans are hidden from new checkouts. Existing subscribers are unaffected.

### Payments (`/admin/payments`)

- View all payments across all users.
- Filter by status (`completed`, `pending`, `failed`) or type (`subscription`, `limit_extension`, `admin_granted`).
- Search by user email.

### Webhook Logs (`/admin/webhook-logs`)

Shows every Facebook webhook event received.

- **Status filter:** `pending`, `processed`, `failed`
- **Event type filter:** `message`, `comment`, `postback`
- **View payload:** Click a log row to see the full raw JSON.
- **Retry a failed event:** Open the log → click **Retry**. Queues the event again for processing.

### AI Logs (`/admin/ai-logs`)

Shows every AI reply attempt.

- **Statuses:** `success`, `failed`, `unknown_answer`
- `unknown_answer` means the AI could not find an answer in the knowledge base (returned `__UNKNOWN__` sentinel — no reply was sent to the user).
- View the full prompt and response for debugging.

### Audit Logs (`/admin/audit-logs`)

Immutable log of all admin actions (user suspend/activate, plan assign, limit extend, plan CRUD).

- Filter by action type or entity.
- Cannot be deleted.

### Email Logs (`/admin/email-logs`)

Log of all outbound emails sent by the system (notifications, invitations).

### System Settings (`/admin/system-settings`)

Key-value configuration store for runtime settings.

**Add a setting:**
1. Click **New Setting**.
2. Enter key (e.g. `maintenance_mode`), value, type (`string`, `integer`, `boolean`, `json`).
3. Check **Sensitive** to mask the value in the UI (stored as plain text — use for non-critical config only).

**Edit:** Click **Edit** on any row → change value → Save.

**Delete:** Click **Delete** → confirm.

### Analytics (`/admin/analytics`)

Platform-wide charts:
- Signups over time
- Revenue over time
- Active subscriptions by plan
- Webhook event volume

---

## Client Admin

Login and land on the dashboard at `/dashboard`.

### Dashboard

Shows your workspace statistics:
- Active conversations, messages today, automation rules active, comment replies today
- Usage bar (how much of your plan limits are consumed)
- Usage warning banner when over 80%

### Facebook Pages (`/facebook/pages`)

**Connect Facebook:**
1. Click **Connect Facebook Account**.
2. Authorise in the Meta popup. Your pages appear in the list.

**Connect a specific page:**
1. Click **Connect** next to the page you want to automate.
2. The page status changes to **Connected**.

**Enable Automation:**
1. On a connected page, click **Enable Automation**.
2. The page now processes incoming messages and comments through your rules.

**Disable Automation:** Stops all automated replies. Messages still arrive in inbox.

**Disconnect a page:** Removes the page connection and token. Automation stops.

> **Note:** The page access token is stored encrypted. It is never visible in the UI or API responses.

### Automation Rules (`/automation-rules`)

Rules are matched in order. First match wins.

**Create a rule:**
1. Click **New Rule**.
2. Select page, type (`message` or `comment`), trigger (`contains`, `exact`, `starts_with`, `ends_with`, `regex`).
3. Enter the keyword(s) to match.
4. Enter the reply text.
5. Set priority (lower number = higher priority).
6. Click **Save**.

**Edit/Delete:** Use the icons on each rule row.

**Rule matching logic:**
- `contains` — message includes the keyword (case-insensitive)
- `exact` — message equals the keyword exactly
- `starts_with` / `ends_with` — self-explanatory
- `regex` — full PHP-compatible regex pattern

### AI Settings (`/ai-settings`)

Configure the AI reply fallback (fires when no rule matches).

- **AI Provider:** OpenAI (default). Key can be set globally or per-page.
- **Reply Length:** Short (100 tokens) / Medium (200) / Long (400).
- **Tone:** Friendly, professional, casual.
- **Language:** e.g. `en`, `bn`, `ar`.
- **Use Emoji:** Toggle.
- **Restricted Instructions:** Additional safety rules for the AI.
- **Test Reply:** Enter a sample question to preview the AI response without sending it.

> **Test mode:** If no OpenAI key is set and `APP_ENV=local`, the AI returns a stub reply instead of failing. Useful for local development.

### Knowledge Base (`/knowledge-base`)

The AI answers questions **only** from the knowledge base. It will not invent information.

**Create a knowledge base:**
1. Select the Facebook page.
2. Click **Create Knowledge Base**.

**Add items:**
1. Click **Add Item**.
2. Enter category (e.g. "Pricing"), title, and content.
3. Status: `active` items are included in AI prompts. `inactive` items are excluded.

**Edit/Delete items:** Use the icons on each row.

**Best practices:**
- Write concise items. The AI includes all active items in every prompt.
- Use clear categories to organise content.
- Keep total content under ~3000 words for best results.

### Inbox (`/inbox`)

Shows all active Messenger conversations across all your connected pages.

**View a conversation:** Click the conversation row to open the message thread.

**Reply:**
1. Type in the reply box → press **Send** or Enter.
2. Your reply is sent via the Facebook Messenger API immediately.

**Use a reply template:**
1. Click the template icon in the reply box.
2. Select a template. Text is inserted into the reply box for editing before sending.

**Assign conversation:** Assign to a team member using the **Assign** dropdown.

**Human Takeover:**
- Click **Enable Human Takeover** to stop automated replies on this conversation.
- Automation resumes only when **Disable Human Takeover** is clicked.

**Close conversation:** Marks it as resolved. Removes from the active list.

**Add note:** Internal note — visible to team members, not sent to the customer.

**Add tag:** Categorise conversations (e.g. "urgent", "sales", "support").

### Comments (`/comments`)

Shows all Facebook post comments across connected pages.

- Filter by page or status.
- View comment text, commenter name, timestamp.
- Automated replies appear in the same list.

### Team (`/team`)

Manage team members who can access your workspace.

**Invite a member:**
1. Click **Invite Member**.
2. Enter email and select role: `agent` or `support-admin`.
3. An invitation email is sent. The invitee clicks the link to create their account and join.

**Roles:**
- `agent` — full inbox access (reply, assign, close, human takeover)
- `support-admin` — inbox read-only

**Remove a member:** Click **Remove** next to the member. They lose access immediately.

**Revoke an invitation:** Click **Revoke** on a pending invitation.

### Reply Templates (`/reply-templates`)

Pre-written messages available in the inbox.

**Create:** Click **New Template** → enter name and body → Save.

**Edit/Delete:** Use the icons on each template.

### Billing (`/billing`)

**Current subscription:** Shows plan, billing cycle, renewal date, limits.

**Upgrade plan:**
1. Select a plan from the list.
2. Choose monthly or yearly.
3. Click **Subscribe**. Redirected to payment gateway.
4. On successful payment, the plan activates immediately.

**Extend limits** (without changing plan):
1. Select an extension package (e.g. "+500 messages").
2. Click **Purchase**. Limits increase immediately after payment.

**Payment history:** Table of all past payments.

### Usage Limits (`/usage-limits`)

Detailed view of current usage vs. limits.

- Message replies used / limit
- Comment replies used / limit
- AI replies used / limit
- Reset date (limits reset monthly)
- Usage history log

**Warning:** At 80% usage, a notification appears in the sidebar. At 100%, automation stops sending replies until limits are extended or reset.

### Notifications

Bell icon in the sidebar shows unread count.

- Usage 80% warning
- Usage limit exceeded
- Payment success
- Team invitation accepted

Mark individual or all as read. Delete unwanted notifications.

### Analytics (`/analytics`)

Your workspace charts:
- Messages received over time
- Automation rule match rate
- AI reply rate
- Comment replies over time

---

## Agent

Agents have access only to the inbox and conversations.

### What agents can do

- **View conversations** in the shared inbox
- **Reply** to conversations (manual send via Messenger API)
- **Assign** conversations to themselves or other team members
- **Close** conversations
- **Add notes** (internal, not sent to customer)
- **Add/remove tags**
- **Enable/disable human takeover** on a conversation
- **View reply templates** and insert them

### What agents cannot do

- View or change automation rules
- View or change AI settings
- View billing or payments
- Invite or remove team members
- Access any admin or analytics page

---

## Support Admin

Support admins can view the inbox but cannot reply.

### What support admins can do

- **View conversations** and message history
- **View notes** on conversations
- **View tags** on conversations

### What support admins cannot do

- Send replies
- Close conversations
- Assign conversations
- Enable/disable human takeover
- Access any settings, billing, or analytics

---

## Common Features

### Notifications (all roles)

Available in the sidebar bell. Shows alerts for events relevant to your role.

### Profile & Password

Click your avatar in the sidebar → **Settings**:
- Change display name
- Change email
- Change password (current password required)

### Logout

Click your avatar → **Logout**.

---

## Frequently Asked Questions

**Q: Why is automation not firing?**
A: Check that: (1) the Facebook page is connected, (2) automation is enabled on that page, (3) at least one rule exists or AI settings are configured, (4) usage limits are not exceeded.

**Q: The AI says it doesn't know something it should know.**
A: Add the information to the Knowledge Base. The AI only answers from knowledge base content — it will not use general knowledge.

**Q: I see `[TEST MODE]` in AI replies.**
A: This means `APP_ENV=local` and no OpenAI API key is configured. Set `OPENAI_API_KEY` in `.env` for real replies.

**Q: A webhook event failed — what do I do?**
A: Go to Admin → Webhook Logs, find the failed event, open it, click **Retry**. Check the error message for the root cause (usually a misconfigured page token or a network timeout).

**Q: How do I switch payment gateway from Stripe to SSLCommerz?**
A: In `.env`, set `PAYMENT_GATEWAY=sslcommerz` and fill in `SSLCOMMERZ_STORE_ID`, `SSLCOMMERZ_STORE_PASSWORD`. Set `SSLCOMMERZ_SANDBOX=false` for production. Restart the server.
