import { Theme, useTheme } from '@/lib/theme';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    FileText,
    GraduationCap,
    LayoutDashboard,
    LogOut,
    LucideIcon,
    Monitor,
    Moon,
    Settings2,
    SquareKanban,
    Sun,
    UserRound,
} from 'lucide-react';
import { PropsWithChildren, ReactNode } from 'react';

const NAV: { label: string; short: string; route: string; match: string; icon: LucideIcon }[] = [
    { label: 'Dashboard', short: 'Home', route: 'dashboard', match: 'dashboard', icon: LayoutDashboard },
    { label: 'Jobs', short: 'Jobs', route: 'jobs.index', match: 'jobs.*', icon: BriefcaseBusiness },
    { label: 'Applications', short: 'Board', route: 'applications.index', match: 'applications.*', icon: SquareKanban },
    { label: 'Resumes', short: 'Resumes', route: 'resumes.index', match: 'resumes.*', icon: FileText },
    { label: 'Interview prep', short: 'Prep', route: 'prep.index', match: 'prep.*', icon: GraduationCap },
    { label: 'Settings', short: 'Settings', route: 'settings.index', match: 'settings.*', icon: Settings2 },
];

const THEME_ICONS: Record<Theme, LucideIcon> = {
    light: Sun,
    dark: Moon,
    system: Monitor,
};

function ThemeButton({ showLabel = false }: { showLabel?: boolean }) {
    const [theme, cycle] = useTheme();
    const Icon = THEME_ICONS[theme];

    return (
        <button
            type="button"
            onClick={cycle}
            aria-label={`Theme: ${theme}. Click to change.`}
            className="flex items-center gap-2.5 rounded-lg p-2 text-sm text-muted transition hover:bg-sunken hover:text-ink"
        >
            <Icon className="h-4 w-4" />
            {showLabel && <span className="capitalize">{theme} theme</span>}
        </button>
    );
}

export function Wordmark() {
    return (
        <span className="font-display text-xl font-bold tracking-tight">
            Job<span className="text-accent">Hunt</span>
        </span>
    );
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage<PageProps>().props.auth.user;

    return (
        <div className="min-h-screen lg:grid lg:grid-cols-[224px_minmax(0,1fr)]">
            <aside className="sticky top-0 hidden h-screen flex-col border-r border-line px-3 py-5 lg:flex">
                <Link href={route('dashboard')} className="px-2.5">
                    <Wordmark />
                </Link>

                <nav className="mt-8 flex flex-col gap-0.5">
                    {NAV.map((item) => {
                        const active = route().current(item.match);

                        return (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                aria-current={active ? 'page' : undefined}
                                className={`flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition ${
                                    active
                                        ? 'bg-ink font-medium text-canvas'
                                        : 'text-muted hover:bg-sunken hover:text-ink'
                                }`}
                            >
                                <item.icon className="h-4 w-4" />
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                <div className="mt-auto flex flex-col gap-0.5 border-t border-line pt-3">
                    <ThemeButton showLabel />
                    <Link
                        href={route('profile.edit')}
                        className="flex items-center gap-2.5 truncate rounded-lg p-2 text-sm text-muted transition hover:bg-sunken hover:text-ink"
                    >
                        <UserRound className="h-4 w-4 shrink-0" />
                        <span className="truncate">{user.name}</span>
                    </Link>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="flex items-center gap-2.5 rounded-lg p-2 text-sm text-muted transition hover:bg-sunken hover:text-ink"
                    >
                        <LogOut className="h-4 w-4" />
                        Log out
                    </Link>
                </div>
            </aside>

            <div className="min-w-0">
                <div className="sticky top-0 z-20 flex items-center justify-between border-b border-line bg-canvas/90 px-4 py-2.5 backdrop-blur lg:hidden">
                    <Link href={route('dashboard')}>
                        <Wordmark />
                    </Link>
                    <div className="flex items-center gap-0.5">
                        <ThemeButton />
                        <Link
                            href={route('profile.edit')}
                            aria-label="Profile"
                            className="rounded-lg p-2 text-muted hover:bg-sunken hover:text-ink"
                        >
                            <UserRound className="h-4 w-4" />
                        </Link>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            aria-label="Log out"
                            className="rounded-lg p-2 text-muted hover:bg-sunken hover:text-ink"
                        >
                            <LogOut className="h-4 w-4" />
                        </Link>
                    </div>
                </div>

                <main className="mx-auto max-w-[1360px] px-4 pb-24 pt-6 sm:px-6 lg:px-10 lg:pb-12 lg:pt-9">
                    {header && <div className="mb-6">{header}</div>}
                    {children}
                </main>
            </div>

            <nav className="fixed inset-x-0 bottom-0 z-20 grid grid-cols-6 border-t border-line bg-surface lg:hidden">
                {NAV.map((item) => {
                    const active = route().current(item.match);

                    return (
                        <Link
                            key={item.route}
                            href={route(item.route)}
                            aria-current={active ? 'page' : undefined}
                            className={`flex flex-col items-center gap-1 py-2 text-[10px] font-medium ${
                                active ? 'text-accent' : 'text-muted'
                            }`}
                        >
                            <item.icon className="h-[18px] w-[18px]" />
                            {item.short}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
