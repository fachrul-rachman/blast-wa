import { Link, router } from '@inertiajs/react';
import {
    LayoutDashboard,
    LogOut,
    Menu,
    MessageSquareText,
    PanelLeftClose,
    PlusCircle,
    Send,
} from 'lucide-react';
import type { PropsWithChildren, ReactNode } from 'react';
import { useState } from 'react';

type AppLayoutProps = PropsWithChildren<{
    title: string;
    actions?: ReactNode;
}>;

const navigation = [
    { href: '/', label: 'Dashboard', icon: LayoutDashboard },
    { href: '/campaigns', label: 'Campaigns', icon: Send },
    { href: '/campaigns/create', label: 'Create Campaign', icon: PlusCircle },
    { href: '/templates', label: 'Templates', icon: MessageSquareText },
];

export default function AppLayout({
    title,
    actions,
    children,
}: AppLayoutProps) {
    const [sidebarOpen, setSidebarOpen] = useState(true);

    function logout() {
        router.post('/logout');
    }

    return (
        <main className="min-h-screen bg-zinc-50 text-zinc-950">
            <div className="flex min-h-screen">
                <aside
                    className={`border-r border-zinc-200 bg-white transition-all ${sidebarOpen ? 'w-64' : 'w-20'}`}
                >
                    <div className="flex h-16 items-center justify-between border-b border-zinc-200 px-4">
                        {sidebarOpen && (
                            <div>
                                <p className="text-sm font-medium text-emerald-700">
                                    Blasting
                                </p>
                                <p className="text-xs text-zinc-500">Message</p>
                            </div>
                        )}
                        <button
                            type="button"
                            onClick={() => setSidebarOpen((value) => !value)}
                            className="inline-flex h-10 w-10 items-center justify-center rounded-md border border-zinc-200 hover:bg-zinc-100"
                            aria-label={
                                sidebarOpen ? 'Close sidebar' : 'Open sidebar'
                            }
                            title={
                                sidebarOpen ? 'Close sidebar' : 'Open sidebar'
                            }
                        >
                            {sidebarOpen ? (
                                <PanelLeftClose size={18} />
                            ) : (
                                <Menu size={18} />
                            )}
                        </button>
                    </div>

                    <nav className="space-y-1 p-3">
                        {navigation.map((item) => {
                            const Icon = item.icon;
                            const active = isActive(item.href);

                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={`flex h-11 items-center gap-3 rounded-md px-3 text-sm font-medium ${active ? 'bg-emerald-50 text-emerald-800' : 'text-zinc-700 hover:bg-zinc-100'}`}
                                    title={item.label}
                                >
                                    <Icon size={18} />
                                    {sidebarOpen && <span>{item.label}</span>}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="border-b border-zinc-200 bg-white">
                        <div className="flex h-16 items-center justify-between px-6">
                            <h1 className="text-xl font-semibold">{title}</h1>
                            <div className="flex items-center gap-2">
                                {actions}
                                <button
                                    type="button"
                                    onClick={logout}
                                    className="inline-flex h-10 items-center gap-2 rounded-md border border-zinc-300 px-3 text-sm font-medium hover:bg-zinc-100"
                                >
                                    <LogOut size={16} />
                                    <span>Logout</span>
                                </button>
                            </div>
                        </div>
                    </header>

                    <section className="min-w-0 flex-1 px-6 py-8">
                        {children}
                    </section>
                </div>
            </div>
        </main>
    );
}

function isActive(href: string): boolean {
    if (href === '/') {
        return window.location.pathname === '/';
    }

    if (href === '/campaigns') {
        return window.location.pathname === '/campaigns';
    }

    return window.location.pathname.startsWith(href);
}
