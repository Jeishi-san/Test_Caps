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
  Image as ImageIcon
} from 'lucide-react';

export default function Authenticated({
  header,
  children,
}: PropsWithChildren<{ header?: ReactNode }>) {
  const user = usePage<PageProps>().props.auth.user;
  const [showMobileSidebar, setShowMobileSidebar] = useState(false);
  const [showNewDropdown, setShowNewDropdown] = useState(false);

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

  return (
    <div className="min-h-screen bg-gray-50 flex">
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

          {/* Storage Indicator */}
          <div className="px-4 py-4 border-t border-gray-200">
            <div className="bg-white rounded-lg p-3 shadow-sm">
              <div className="flex items-center justify-between mb-2">
                <div className="flex items-center gap-2">
                  <HardDrive className="w-4 h-4 text-gray-500" />
                  <span className="text-xs font-medium text-gray-600">Storage</span>
                </div>
                <span className="text-xs text-gray-500">{storageUsed} GB / {storageTotal} GB</span>
              </div>

              <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                <div
                  className={`h-full rounded-full transition-all duration-500 ease-out ${
                    storageCritical ? 'bg-red-500' : 'bg-gradient-to-r from-indigo-500 to-purple-600'
                  }`}
                  style={{ width: `${storagePercent}%` }}
                />
              </div>

              <button className="mt-2 w-full text-xs text-indigo-600 font-medium hover:text-indigo-800">
                Manage storage
              </button>
            </div>
          </div>

          {/* User Account Footer */}
          <div className="border-t border-gray-200 px-4 py-3">
            <Dropdown>
              <Dropdown.Trigger>
                <button className="w-full flex items-center gap-3 p-2 rounded-lg hover:bg-gray-200/60 transition-colors">
                  <div className="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-sm font-semibold">
                    {user.name.charAt(0).toUpperCase()}
                  </div>
                  <div className="flex-1 text-left">
                    <p className="text-sm font-medium text-gray-800 truncate">{user.name}</p>
                    <p className="text-xs text-gray-500 truncate">{user.email}</p>
                  </div>
                  <ChevronDown className="w-4 h-4 text-gray-500" />
                </button>
              </Dropdown.Trigger>

              <Dropdown.Content align="right" width="48">
                <div className="border-b border-gray-100 px-4 py-2">
                  <div className="text-sm font-medium text-gray-800">{user.name}</div>
                  {user.role && (
                    <span className="mt-1 inline-block rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium capitalize text-indigo-700">
                      {user.role}
                    </span>
                  )}
                </div>
                <Dropdown.Link href={route('profile.edit', undefined, false)}>
                  <User className="w-4 h-4 mr-2" />
                  Profile
                </Dropdown.Link>
                <Dropdown.Link href={route('settings.index', undefined, false)}>
                  <Settings className="w-4 h-4 mr-2" />
                  Settings
                </Dropdown.Link>
                <Dropdown.Link href={route('stego.tokens', undefined, false)}>
                  <Key className="w-4 h-4 mr-2" />
                  API Tokens
                </Dropdown.Link>
                <Dropdown.Link href={route('logout', undefined, false)} method="post" as="button">
                  <LogOut className="w-4 h-4 mr-2" />
                  Log Out
                </Dropdown.Link>
              </Dropdown.Content>
            </Dropdown>
          </div>
        </div>
      </aside>

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Top Header Bar */}
        <header className="h-16 bg-white border-b border-gray-200 flex items-center px-4 lg:px-6 shadow-sm">
          {/* Mobile Menu Toggle */}
          <button
            onClick={() => setShowMobileSidebar(true)}
            className="lg:hidden text-gray-500 hover:text-gray-700 p-2 -ml-2"
          >
            <Menu className="w-5 h-5" />
          </button>

          <div className="flex-1" />

          {/* Right Side Actions */}
          <div className="flex items-center gap-4">
            <NotificationBell />
          </div>
        </header>

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
    </div>
  );
}
