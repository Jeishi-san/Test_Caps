StegoLock Admin Interface - Complete UI/UX Design Elements
Overall Theme & Visual Identity
Color Scheme
Primary Background: Dark cybersecurity theme with slate-950, slate-900, slate-800 gradients
Accent Colors:
Orange/Red gradient (from-red-500 to-orange-600) for primary actions
Red (red-600) for Superadmin-exclusive elements
Blue (blue-600) for Admin role indicators
Green for success states
Yellow for warnings
Red for errors/critical states
Visual Effects
Glassmorphism: backdrop-blur-xl effects on containers
Transparency: bg-opacity variations (900/50, 800/50)
Borders: Subtle slate-700/50, slate-800 borders
Shadows: shadow-2xl on cards and modals
Hover States: Smooth transitions on all interactive elements
Authentication & Login
Admin Login Page
Layout: Centered card on gradient background (from-slate-950 via-slate-900 to-slate-800)

Decorative Background: Animated background component

Login Card:

Glassmorphic container (bg-slate-900/90 backdrop-blur-xl)
Rounded-2xl corners
Border: slate-700/50
Header Section:

Shield icon in gradient background (from-red-500 to-orange-600)
Large "StegoLock Admin" title (text-3xl font-bold)
Subtitle: "Secure System Administration" (text-slate-400)
Form Elements:

Email input with Mail icon
Password input with Lock icon
Icon positioning: absolute left-3
Input styling: bg-slate-800/50, slate-700 borders, white text
Focus states: orange-500/50 ring
Placeholder text in slate-500
Submit Button:

Full-width gradient (from-red-600 to-orange-600)
Hover effect: from-red-700 to-orange-700
Shadow-lg with hover:shadow-xl
Footer: "Authorized personnel only" message in slate-500

Layout Structure
Main Admin Layout
Overall Container: Full-screen flex layout with slate-950 background
Three Main Sections:
Sidebar (left, fixed width)
Top bar (horizontal across top)
Main content area (flex-1, scrollable)
Sidebar Navigation
Visual Design
Container:
Width: w-64 (256px)
Background: slate-900
Right border: slate-800
Full height flex column
Header Section
Brand Area:
Padding: p-6
Border bottom: slate-800
Logo text: "StegoLock" (text-xl font-bold white)
Subtitle: "Administration Panel" (text-xs slate-400)
Navigation Items
Admin Navigation (Available to all):

Dashboard (LayoutDashboard icon)
Users (Users icon)
Fragment Monitoring (Database icon)
Activity Logs (Activity icon)
Incidents (AlertTriangle icon)
Superadmin-Only Navigation:

Visual separator with border-t slate-800
Section label: "SUPERADMIN ONLY" (uppercase, tracked, slate-500)
Admin Management (UserCog icon)
Encryption Policy (Lock icon)
Key Management Policy (Key icon)
Storage Configuration (HardDrive icon)
System Configuration (Settings icon)
Disaster Recovery (Archive icon)
Navigation Styling
Active State:
Admin items: orange-600 background
Superadmin items: red-600 background
White text
Inactive State:
slate-400 text
Hover: slate-800 background, white text
Item Layout:
Flex with gap-3
Padding: px-4 py-3
Rounded-lg
Smooth transitions
Footer Section
Logout Button:
Full width
Flex layout with LogOut icon
slate-400 text, hover to white
Hover: slate-800 background
Top Bar
Design
Background: slate-900/50 with backdrop-blur-xl
Border: Bottom border slate-800
Padding: px-6 py-4
Content
User Information (right-aligned):
User email (text-sm white font-medium)
Role badge below email
Role Badges
Superadmin Badge:
Background: red-600/20
Text: red-400
Border: red-600/30
Text: "SUPERADMIN"
Admin Badge:
Background: blue-600/20
Text: blue-400
Border: blue-600/30
Text: "ADMIN"
Dashboard Page
Stats Grid
Layout: Grid (1/2/4 columns responsive)
Stat Cards:
Background: slate-900/50
Border: slate-800
Rounded-xl
Padding: p-6
Hover: slate-700 border
Stat Card Elements
Label: slate-400 text-sm
Value: text-3xl font-bold white
Trend Indicator:
Green for positive (green-400)
Red for negative (red-400)
Small font with "vs last month" in slate-500
Icon Badge:
Rounded-lg with color-coded backgrounds
Blue/Green/Purple/Red with 20% opacity
Icon in matching color (size-6)
System Health Section
Container: slate-900/50 background, rounded-xl
Header: Activity icon with "System Health" title
Health Items:
Status indicator dots (green/yellow/red)
Service name (slate-300)
Percentage value (white font-medium)
Status badges (operational/degraded)
Recent Activity Section
Container: slate-900/50 background, rounded-xl
Header: Database icon with "Recent Activity" title
Activity Items:
Color-coded status dots with backgrounds
Action description (slate-300)
User and timestamp (slate-500, text-xs)
Users Page
Page Header
Title: "Users" (text-3xl font-bold white)
Subtitle: "Manage user accounts and permissions" (slate-400)
Create Button:
Orange-600 background
Plus icon
"Create User" text
Hover: orange-700
Filter Section
Container: slate-900/50, rounded-xl, padding p-4

Search Input:

Search icon (absolute left-3)
Full-width on mobile, flex-1 on desktop
Placeholder: "Search users..."
Background: slate-800/50
Focus ring: orange-500/50
Status Filter Dropdown:

Options: All Status, Active, Suspended
Matching input styling
Users Table
Container: slate-900/50, rounded-xl, overflow-hidden

Table Header:

Background: slate-800/50
Border-bottom: slate-700
Column headers: uppercase, tracked, slate-400 text-xs
Columns: User, Status, Containers, Last Activity, Created, Actions
Table Rows:

Hover: slate-800/30 background
User cell: Name (white font-medium), email (slate-400 text-xs)
Status badges: Green (active), Red (suspended)
Action buttons: Eye, RotateCcw, Ban icons
Icon hover: slate-700 background
Create User Modal
Overlay: black/60 with backdrop-blur-sm, z-index 150

Modal Card:

slate-900 background
slate-800 border
Rounded-xl, shadow-2xl
Max-width: md
Modal Header:

Title: "Create New User"
Subtitle: "Add a new user account"
Border-bottom: slate-800
Form Fields:

Full Name input
Email input
Initial Password input
All with slate-800/50 backgrounds
Orange-500/50 focus rings
Action Buttons:

Cancel: slate-800, hover slate-700
Create: orange-600, hover orange-700
Equal width (flex-1)
Fragment Monitoring Page
Stats Cards (4-column grid)
Total Fragments: Default styling
Healthy: green-800/30 border, green-400 text
Warning: yellow-800/30 border, yellow-400 text
Error: red-800/30 border, red-400 text
Fragments Table
Columns: Fragment ID, Container, User, Carrier Type, Status, Integrity, Size, Last Check
Fragment ID: font-mono, white
Container ID: font-mono, slate-300
Carrier Type Badge: slate-800 background, slate-300 text
Status Icons: CheckCircle (green), AlertTriangle (yellow), XCircle (red)
Integrity Bar:
Background: slate-700
Fill color based on percentage (green/yellow/red)
Width: max-w-[100px]
Height: h-2, rounded-full
Activity Logs Page
Filter Controls
Three Filters:
Search input (Search icon)
Action filter dropdown (Container/User/Login/Backup actions)
Status filter dropdown (Success/Error)
Activity Logs Table
Columns: Timestamp, User, Action, Details, Status, IP Address
Timestamp: font-mono, slate-300
User: white text-sm
Action: font-medium, slate-300
Details: slate-400
Status Badges: Green (success), Red (error)
IP Address: font-mono, slate-400
Incidents Page
Incident Stats (4-column grid)
Total Incidents: Default
Open: red-800/30 border, red-400 text
Investigating: yellow-800/30 border, yellow-400 text
Resolved: green-800/30 border, green-400 text
Incidents Table
Columns: Incident ID, Timestamp, User, Type, Severity, Status, Actions
Incident ID: font-mono, white
Type Cell: Title (slate-300 font-medium), Details below (slate-500 text-xs)
Severity Badges:
Critical: red-600/20, red-400 with border
High: orange-600/20, orange-400 with border
Medium: yellow-600/20, yellow-400 with border
Low: blue-600/20, blue-400 with border
Action Icons: Flag (yellow), Ban (red), AlertCircle (orange)
Admin Management Page (Superadmin Only)
Page Header
Create Button: red-600 (Superadmin color), hover red-700
Admin Table
Columns: Admin, Role, MFA, Last Login, Created, Actions
Role Badge:
Blue-600/20 background
Blue-400 text
Shield icon included
MFA Toggle Button:
Enabled: green-600/20, green-400
Disabled: slate-700, slate-400
Clickable to toggle
Delete Button: Trash2 icon in red-400
Create Admin Modal
Similar to Create User Modal but with:
Red-600 submit button (Superadmin color)
Red-500/50 focus rings
MFA Required checkbox
Encryption Policy Page (Superadmin Only)
Configuration Form
AES Mode Field:

Read-only input
Active badge (green-600/20, green-400) with Lock icon
Key Size Dropdown:

Options: 128/192/256-bit
Helper text below inputs
KDF Iterations:

Number input (min 10,000, max 1,000,000)
Fragment Size:

Two-column grid
Min and Max inputs with MB units
Warning Banner
Background: yellow-600/10
Border: yellow-600/30
Icon: Info icon in yellow-400
Text: yellow-400 and yellow-400/80
Save Button
Red-600 background (Superadmin action)
Save icon included
Status Section
Two stat cards:
Active Containers count
Average Encryption Time
Key Management Policy Page (Superadmin Only)
Password Strength Rules
Min Length Input: Number input (8-32 range)
Character Requirements:
Four checkboxes (Uppercase, Lowercase, Numbers, Special chars)
Custom checkbox styling
Key Rotation Policy
Expiration Period Input: Number input (30-365 days)
Force Rotation Checkbox
Warning Days Input: Number input (1-30 days)
Zero-Knowledge Banner
Background: red-600/10
Border: red-600/30
Icon: AlertCircle in red-400
Title: "Zero-Knowledge Principle"
Emphasis on security architecture
Key Statistics
Three stat cards:
Keys Expiring Soon (yellow-400)
Active Keys (green-400)
Rotations This Month (blue-400)
Storage Configuration Page (Superadmin Only)
Provider Settings
Storage Provider Dropdown: AWS S3, Azure, GCP, Custom
API Key Input:
Password/text toggle with Eye/EyeOff icons
Font-mono styling
Revoke button (red-600/20, red-400)
Region Input: Text input
Bucket Input: Text input
Encryption Dropdown: AES-256/128/None options
Action Buttons
Test Connection: slate-800, RefreshCw icon
Save Configuration: red-600, Save icon
Storage Health Monitoring
6-metric grid (1/2/3 columns responsive):
Available Space
Used Space
Fragment Count
Redundancy Level
Average Latency
Failed Writes (24h)
Health indicator dots: Green for healthy
System Configuration Page (Superadmin Only)
Maintenance Mode Toggle
Large Toggle Section:
Left: Title and description
Right: Toggle button
Enabled: red-600 background with ToggleRight icon
Disabled: slate-800 background with ToggleLeft icon
File and Fragment Limits
Max File Size Input: 1-1000 MB range
Max Fragments Input: 1-100 range
User and Session Settings
Allow Registration Toggle:
Enabled: green-600/20, green-400
Disabled: red-600/20, red-400
Session Timeout: 5-120 minutes range
Rate Limiting: 10-1000 requests/minute
System Information
Three stat cards:
System Version
Uptime
Last Backup
Disaster Recovery Page (Superadmin Only)
Backup Management Header
Action Buttons:
Restore from Backup (slate-800, Upload icon)
Create Backup (red-600, Archive icon)
Loading state: "Creating..." with disabled state
Warning Banner
yellow-600/10 background
Important Notice about zero-knowledge
Explains what's NOT backed up
Backup History Table
Columns: Backup ID, Timestamp, Type, Size, Status, Actions
Backup ID: font-mono
Type Badge:
Manual: blue-600/20, blue-400
Automated: slate-700, slate-300
Status: Green badges for completed
Download Action: Download icon button
System Audit Logs Table
Columns: Timestamp, User, Action, Details, Severity
Severity Badges:
High: red-600/20, red-400
Medium: yellow-600/20, yellow-400
Low: blue-600/20, blue-400
Typography System
Headings
Page Titles: text-3xl, font-bold, white
Page Subtitles: text-slate-400
Section Titles: text-xl or text-lg, font-semibold, white
Section Subtitles: text-sm, slate-400
Body Text
Primary: text-sm, white or slate-300
Secondary: text-xs, slate-400 or slate-500
Monospace: font-mono for IDs, timestamps, technical data
Icon System
Icon Library
Lucide React icons throughout
Consistent sizing: size-4, size-5, size-6
Color coordination with context
Common Icons
Navigation: Specific icons per page
Actions: Plus, Trash2, Eye, Ban, Flag, etc.
Status: CheckCircle, AlertTriangle, XCircle, AlertCircle
Settings: Save, Settings, Lock, Key, Shield
Interactive Elements
Buttons
Primary Actions: Orange/Red gradients
Secondary Actions: slate-800 backgrounds
Danger Actions: Red-600
Success States: Green variants
All include:
Rounded-lg corners
Padding: px-4 py-2.5 or px-6 py-2.5
Transition-all for smooth effects
Hover state darkening
Icon + text combinations
Form Inputs
Text/Number/Email/Password:
bg-slate-800/50
border-slate-700
rounded-lg
white text
placeholder-slate-500
focus:ring-2 with color-appropriate ring
focus:outline-none
Dropdowns/Selects
Same styling as inputs
Consistent focus states
Checkboxes
Custom styling:
w-4 h-4
bg-slate-800
border-slate-700
rounded
Tables
Table Structure
Container: rounded-xl, overflow-hidden
Header:
bg-slate-800/50
border-b slate-700
Uppercase, tracked headers
slate-400 text
text-xs font-semibold
Body:
divide-y slate-800
Row hover: slate-800/30
Table Cells
Padding: px-4 py-3 or px-6 py-4
Text alignment: Left for most, right for actions
Consistent typography
Badges & Status Indicators
Badge Styles
Rounded-full for status tags
Rounded (regular) for type tags
Inline-flex for proper icon alignment
Padding: px-2.5 py-1 or px-2 py-1
Text: text-xs font-medium
Color Coding
Success/Active/Healthy: green-600/20 bg, green-400 text
Warning/Degraded: yellow-600/20 bg, yellow-400 text
Error/Critical/Suspended: red-600/20 bg, red-400 text
Info/Default: blue-600/20 bg, blue-400 text
Neutral: slate-700 bg, slate-300 text
Modals & Overlays
Modal Structure
Overlay:

fixed inset-0
bg-black/60
backdrop-blur-sm
z-[150]
Centered flex layout
Modal Card:

bg-slate-900
border slate-800
rounded-xl
shadow-2xl
max-w-md
Modal Sections
Header:

p-6
border-b slate-800
Title + subtitle
Body:

p-6
Form or content area
Footer/Actions:

Button row with gap-3
Cancel + Submit pattern
Responsive Design
Grid Breakpoints
Stats: 1 column → 2 columns (md) → 4 columns (lg)
Content Sections: 1 column → 2 columns (lg)
Tables: Horizontal scroll on mobile (overflow-x-auto)
Filter Controls
Stack vertically on mobile (flex-col)
Horizontal on desktop (flex-row)
Spacing & Layout
Container Spacing
Page padding: p-6 on main content area
Section gaps: space-y-6 between major sections
Card padding: p-4, p-5, or p-6 depending on importance
Component Gaps
Button groups: gap-2 or gap-3
Form fields: space-y-4 or space-y-6
Grid gaps: gap-4 or gap-6
Security-Focused Visual Language
Design Principles
Dark, professional cybersecurity aesthetic
Clear role differentiation (Orange for Admin, Red for Superadmin)
Zero-knowledge warnings prominently displayed
Status clarity through consistent color coding
Progressive disclosure with collapsible sections
Monospace fonts for technical/cryptographic data
Minimal decoration, maximum clarity
Accessibility Features
Interactive Elements
Hover states on all clickable elements
Focus rings on all inputs
Transition effects for smooth feedback
Icon + text labels for clarity
Title attributes on icon-only buttons
Semantic HTML structure
Color contrast meeting WCAG standards with dark theme
