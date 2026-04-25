import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import NotificationBell from '@/Components/NotificationBell';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';
import { PageProps } from '@/types';
import {
  Shield,
  Plus,
  Folder,
  FolderOpen,
  Star,
  Users,
  ChevronDown,
  LogOut,
  User,
  Settings,
  Key,
  Menu,
  X,
  HardDrive,
  Lock,
  Unlock,
  Image as ImageIcon,
  CheckCircle2,
  AlertTriangle,
  Eye,
  Grid3x3,
  List,
  Search,
  SlidersHorizontal
} from 'lucide-react';

export default function Authenticated({
  header,
  children,
}: PropsWithChildren<{ header?: ReactNode }>) {
  const user = usePage<PageProps>().props.auth.user;
  const [showMobileSidebar, setShowMobileSidebar] = useState(false);
  const [showNewDropdown, setShowNewDropdown] = useState(false);
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
  const [showSearchFilter, setShowSearchFilter] = useState(false);

  // Mock storage values - will be replaced with real API data
  const storageUsed = 2.4;
  const storageTotal = 10;
  const storagePercent = (storageUsed / storageTotal) * 100;
  const storageCritical = storagePercent > 90;

  const navigationItems = [
    { label: 'My Documents', icon: Folder, href: route('documents.index', undefined, false), active: route().current('documents.index') },
    { label: 'My Folders', icon: FolderOpen, href: route('folders.index', undefined, false), active: route().current('folders.index') },
    { label: 'Starred', icon: Star, href: route('starred.index', undefined, false), active: route().current('starred.index') },
    { label: 'Shared With Me', icon: Users, href: '#', active: false },
  ];

  const stegoNavigationItems = [
    { label: 'Lock a File', icon: Lock, href: route('stego.encode.form', undefined, false), active: route().current('stego.encode.form') },
    { label: 'Unlock a File', icon: Unlock, href: route('stego.decode.form', undefined, false), active: route().current('stego.decode.form') },
    { label: 'Carrier Pool', icon: ImageIcon, href: route('stego.carriers', undefined, false), active: route().current('stego.carriers') },
    { label: 'API Tokens', icon: Key, href: route('stego.tokens', undefined, false), active: route().current('stego.tokens') },
  ];

  const [showSecurityPanel, setShowSecurityPanel] = useState(false);

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-100 flex">
      {/* Mobile Overlay */}
      {showMobileSidebar && (
        <div
          className="fixed inset-0 bg-black/50 z-40 lg:hidden"
          onClick={() => setShowMobileSidebar(false)}
        />
      )}

      {/* Sidebar - Left Fixed Panel */}
      <aside
        className={`fixed inset-y-0 left-0 z-50 w-64 bg-gray-100 shadow-lg transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static ${
          showMobileSidebar ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="h-full flex flex-col">
          {/* Brand Header */}
          <div className="h-16 px-5 flex items-center gap-3 border-b border-gray-200">
            <div className="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-md">
              <Shield className="w-5 h-5 text-white" />
            </div>
            <span className="font-bold text-lg text-gray-800">Stegolock</span>

            {/* Mobile Close Button */}
            <button
              onClick={() => setShowMobileSidebar(false)}
              className="ml-auto lg:hidden text-gray-500 hover:text-gray-700"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Primary Action Button */}
          <div className="px-4 py-4">
            <div className="relative">
              <button
                onClick={() => setShowNewDropdown(!showNewDropdown)}
                className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-lg font-medium shadow-md hover:shadow-lg transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
              >
                <Plus className="w-4.5 h-4.5" />
                <span>New</span>
                <ChevronDown className={`w-4 h-4 transition-transform duration-200 ${showNewDropdown ? 'rotate-180' : ''}`} />
              </button>

              {/* New Dropdown Menu */}
              {showNewDropdown && (
                <div className="absolute top-full left-0 right-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-10">
                  <button className="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3">
                    <Lock className="w-4 h-4 text-indigo-500" />
                    Lock a File
                  </button>
                  <button className="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3">
                    <Unlock className="w-4 h-4 text-indigo-500" />
                    Unlock a File
                  </button>
                  <button className="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3">
                    <ImageIcon className="w-4 h-4 text-indigo-500" />
                    Carrier Pool
                  </button>
                  <div className="border-t border-gray-100 my-1" />
                  <Link
                    href={route('folders.index', undefined, false)}
                    className="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
                    onClick={() => setShowNewDropdown(false)}
                  >
                    <Folder className="w-4 h-4 text-indigo-500" />
                    New Folder
                  </Link>
                </div>
              )}
            </div>
          </div>

          {/* Navigation Links */}
          <nav className="flex-1 px-3 py-2 space-y-1 overflow-y-auto">
            {navigationItems.map((item) => (
              <Link
                key={item.label}
                href={item.href}
                className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 ${
                  item.active
                    ? 'bg-white text-indigo-600 shadow-sm'
                    : 'text-gray-600 hover:bg-gray-200/60 hover:text-gray-900'
                }`}
              >
                <item.icon className="w-5 h-5" />
                {item.label}
              </Link>
            ))}




          </nav>

          {/* Storage Section (Bottom) */}
          <div className="p-5 border-t border-gray-200/50 mt-auto">
            <div className="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-4">
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium text-gray-600">Storage</span>
                <span className="text-sm font-semibold text-gray-900">{storageUsed} GB / {storageTotal} GB</span>
              </div>

              <div className="h-2.5 bg-gray-300 rounded-full overflow-hidden">
                <div
                  className="h-full rounded-full bg-gradient-to-r from-indigo-600 to-purple-600 shadow-sm transition-all duration-500 ease-out"
                  style={{ width: `${storagePercent}%` }}
                />
              </div>

              <button className="mt-3 w-full flex items-center justify-center gap-2 text-gray-700 hover:bg-gray-100 rounded-xl py-2.5 transition-colors">
                <HardDrive className="w-4 h-4" />
                <span className="text-sm">Manage Storage</span>
              </button>
            </div>
          </div>
        </div>
      </aside>

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Top Header Bar */}
        <header className="h-16 bg-white/70 backdrop-blur-xl border-b border-gray-200/50 flex items-center px-8 py-5 shadow-sm">
          {/* Mobile Menu Toggle */}
          <button
            onClick={() => setShowMobileSidebar(true)}
            className="lg:hidden text-gray-500 hover:text-gray-700 p-2 -ml-2"
          >
            <Menu className="w-5 h-5" />
          </button>

          {/* Page Title */}
          {header && (
            <h1 className="text-2xl font-semibold text-gray-900 ml-4 lg:ml-0">
              {header}
            </h1>
          )}

          <div className="flex-1" />

          {/* Right Controls */}
          <div className="flex items-center gap-4">
            {/* View Mode Toggle */}
            <div className="bg-gray-100 rounded-lg p-1 flex items-center gap-1">
              <button
                onClick={() => setViewMode('grid')}
                className={`p-2 rounded-md transition-colors ${viewMode === 'grid' ? 'bg-white shadow-sm' : 'hover:bg-gray-200'}`}
              >
                <Grid3x3 className="w-4 h-4 text-gray-600" />
              </button>
              <button
                onClick={() => setViewMode('list')}
                className={`p-2 rounded-md transition-colors ${viewMode === 'list' ? 'bg-white shadow-sm' : 'hover:bg-gray-200'}`}
              >
                <List className="w-4 h-4 text-gray-600" />
              </button>
            </div>

            {/* User Profile Dropdown */}
            <Dropdown>
              <Dropdown.Trigger>
                <button className="w-9 h-9 rounded-full bg-blue-600 hover:bg-blue-700 text-white flex items-center justify-center text-sm font-semibold uppercase">
                  {user.name.charAt(0).toUpperCase()}
                </button>
              </Dropdown.Trigger>

              <Dropdown.Content align="right" width="48">
                <div className="px-4 py-3 border-b border-gray-200">
                  <p className="text-sm font-medium text-gray-900 truncate">{user.name}</p>
                  <p className="text-xs text-gray-500 truncate">{user.email}</p>
                </div>
                <Dropdown.Link href={route('profile.edit', undefined, false)}>
                  <Settings className="w-4 h-4 mr-2" />
                  Manage Account
                </Dropdown.Link>
                <Dropdown.Link href={route('settings.index', undefined, false)}>
                  <HardDrive className="w-4 h-4 mr-2" />
                  Manage Storage
                </Dropdown.Link>
                <div className="border-t border-gray-200" />
                <Dropdown.Link href={route('logout', undefined, false)} method="post" as="button" className="text-red-600 hover:bg-red-50">
                  <LogOut className="w-4 h-4 mr-2" />
                  Log Out
                </Dropdown.Link>
              </Dropdown.Content>
            </Dropdown>

            <NotificationBell />
          </div>
        </header>

        {/* Search Bar */}
        <div className="px-8 py-4 flex gap-2">
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input
              type="text"
              placeholder="Search documents..."
              className="w-full h-12 pl-10 pr-4 rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
          </div>
          <button
            onClick={() => setShowSearchFilter(!showSearchFilter)}
            className={`flex items-center gap-2 px-4 py-2 rounded-lg border transition-colors ${showSearchFilter ? 'bg-indigo-50 border-indigo-200 text-indigo-700' : 'bg-white border-gray-300 hover:bg-gray-50'}`}
          >
            <SlidersHorizontal className="w-4 h-4" />
            <span>Filters</span>
            {showSearchFilter && <ChevronDown className="w-4 h-4 rotate-180" />}
            {!showSearchFilter && <ChevronDown className="w-4 h-4" />}
          </button>

          {/* Filter Dropdown Panel */}
          {showSearchFilter && (
            <div className="absolute top-full right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 z-[150] p-4">
              <div className="flex items-center justify-between border-b border-gray-200 pb-3">
                <h3 className="font-semibold text-gray-900">Filters & Sort</h3>
                <button className="text-xs text-indigo-600">
                  <X className="w-4 h-4" />
                </button>
              </div>
              <div className="py-3">
                <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Sort By</h4>
                <button className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg">Name (A-Z)</button>
                <button className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg">Name (Z-A)</button>
                <button className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg">Date (Newest First)</button>
                <button className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg">Date (Oldest First)</button>
              </div>
            </div>
          )}
        </div>

        {/* Page Header */}
        {header && (
          <header className="bg-white border-b border-gray-100">
            <div className="px-6 py-6">
              {header}
            </div>
          </header>
        )}

        {/* Page Content */}
        <main className="flex-1 p-6">
          {children}
        </main>
      </div>

      {/* Security Panel Floating Button */}
      <button
        onClick={() => setShowSecurityPanel(true)}
        className="fixed bottom-6 right-6 z-50 bg-green-600 hover:bg-green-700 text-white p-4 rounded-full shadow-lg hover:shadow-xl transition-all"
      >
        <Shield className="w-6 h-6" />
      </button>

      {/* Security Slide-Out Panel */}
      {showSecurityPanel && (
        <div className="fixed inset-y-0 right-0 w-full max-w-md bg-white shadow-2xl z-50 overflow-auto">
          {/* Panel Header */}
          <div className="bg-gradient-to-r from-green-600 to-green-700 p-6 border-b border-green-800">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-3 bg-white/20 rounded-xl">
                  <Shield className="w-6 h-6 text-white" />
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-white">Security Center</h3>
                  <p className="text-sm text-green-100">Your data is protected</p>
                </div>
              </div>
              <button
                onClick={() => setShowSecurityPanel(false)}
                className="text-white hover:text-green-200 transition-colors"
              >
                <X className="w-6 h-6" />
              </button>
            </div>

            {/* Status Card */}
            <div className="mt-4 bg-white/10 backdrop-blur-sm rounded-lg p-4">
              <div className="flex items-center gap-2">
                <CheckCircle2 className="w-5 h-5 text-green-200" />
                <div>
                  <p className="font-semibold text-white">All Systems Operational</p>
                  <p className="text-sm text-green-100">Military-grade encryption</p>
                </div>
              </div>
            </div>
          </div>

          {/* Security Features List */}
          <div className="p-6">
            <h4 className="text-sm font-semibold text-gray-500 uppercase mb-4">Security Features</h4>
            <div className="space-y-4">
              {[
                { icon: Lock, title: 'End-to-End Encryption', desc: 'All files encrypted locally' },
                { icon: Key, title: 'Zero-Knowledge Architecture', desc: 'We never access your keys' },
                { icon: Shield, title: 'Secure File Transfer', desc: 'TLS 1.3 for all transfers' },
                { icon: Eye, title: 'Privacy Protection', desc: 'No third-party sharing' },
              ].map((feature, index) => (
                <div key={index} className="bg-gray-50 rounded-xl p-4">
                  <div className="flex items-center gap-3">
                    <div className="p-2 bg-green-100 rounded-lg">
                      <feature.icon className="w-5 h-5 text-green-600" />
                    </div>
                    <div className="flex-1">
                      <h5 className="font-medium text-gray-900">{feature.title}</h5>
                      <p className="text-sm text-gray-600">{feature.desc}</p>
                    </div>
                    <div className="flex items-center gap-1">
                      <div className="w-2 h-2 rounded-full bg-green-500" />
                      <span className="text-xs font-semibold text-green-700 uppercase">Active</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>

            {/* Security Best Practices */}
            <div className="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
              <div className="flex items-center gap-2 mb-3">
                <AlertTriangle className="w-5 h-5 text-blue-600" />
                <h5 className="font-semibold text-blue-900">Security Best Practices</h5>
              </div>
              <ul className="space-y-1 text-sm text-blue-800">
                <li>• Never share your credentials with anyone</li>
                <li>• Use strong, unique passwords for your account</li>
                <li>• Enable two-factor authentication when available</li>
                <li>• Regularly review access logs for suspicious activity</li>
              </ul>
            </div>

            {/* Encryption Details Card */}
            <div className="mt-6 bg-gradient-to-br from-gray-900 to-gray-800 text-white rounded-xl p-4">
              <div className="flex items-center gap-2 mb-4">
                <Lock className="w-5 h-5" />
                <h5 className="font-semibold">Encryption Details</h5>
              </div>
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p className="text-gray-400">Algorithm</p>
                  <p className="font-medium">AES-256-GCM</p>
                </div>
                <div>
                  <p className="text-gray-400">Key Size</p>
                  <p className="font-medium">256 bits</p>
                </div>
                <div>
                  <p className="text-gray-400">Transport</p>
                  <p className="font-medium">TLS 1.3</p>
                </div>
                <div>
                  <p className="text-gray-400">Compliance</p>
                  <p className="font-medium">SOC 2, GDPR</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
