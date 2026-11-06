import { Platform, RoundType, Status } from '@/types/models';

export const TIMEZONE = 'Asia/Kolkata';

export const STATUS_LABELS: Record<Status, string> = {
    found: 'Found',
    queued: 'Queued',
    applied: 'Applied',
    online_test: 'Online test',
    interview: 'Interview',
    hr: 'HR',
    offer: 'Offer',
    rejected: 'Rejected',
    withdrawn: 'Withdrawn',
    ghosted: 'Ghosted',
};

export const PLATFORM_LABELS: Record<Platform, string> = {
    linkedin: 'LinkedIn',
    naukri: 'Naukri',
    indeed: 'Indeed',
    instahyre: 'Instahyre',
    wellfound: 'Wellfound',
    foundit: 'Foundit',
    career_page: 'Career page',
    other: 'Other',
};

export const ROUND_LABELS: Record<RoundType, string> = {
    aptitude: 'Aptitude',
    coding: 'Coding',
    technical: 'Technical',
    hr: 'HR',
    managerial: 'Managerial',
    group_discussion: 'Group discussion',
};

export type ScoreBand = 'strong' | 'good' | 'fair' | 'low';

export const BAND_LABELS: Record<ScoreBand, string> = {
    strong: 'Strong, 80+',
    good: 'Good, 60-79',
    fair: 'Fair, 40-59',
    low: 'Low, under 40',
};

export function scoreBand(score: number): ScoreBand {
    if (score >= 80) return 'strong';
    if (score >= 60) return 'good';
    if (score >= 40) return 'fair';

    return 'low';
}

const dateFormat = new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'short',
    timeZone: TIMEZONE,
});

const dateTimeFormat = new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'short',
    hour: 'numeric',
    minute: '2-digit',
    timeZone: TIMEZONE,
});

export function formatDate(iso: string | null | undefined): string {
    return iso ? dateFormat.format(new Date(iso)) : '';
}

export function formatDateTime(iso: string | null | undefined): string {
    return iso ? dateTimeFormat.format(new Date(iso)) : '';
}

export function isPast(iso: string): boolean {
    return new Date(iso).getTime() < Date.now();
}

export function titleCase(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}
