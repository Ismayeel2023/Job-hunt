export type Status =
    | 'found'
    | 'queued'
    | 'applied'
    | 'online_test'
    | 'interview'
    | 'hr'
    | 'offer'
    | 'rejected'
    | 'withdrawn'
    | 'ghosted';

export type Platform =
    | 'linkedin'
    | 'naukri'
    | 'indeed'
    | 'instahyre'
    | 'wellfound'
    | 'foundit'
    | 'career_page'
    | 'other';

export type RoundType =
    | 'aptitude'
    | 'coding'
    | 'technical'
    | 'hr'
    | 'managerial'
    | 'group_discussion';

export interface Company {
    id: number;
    name: string;
    website?: string | null;
    location?: string | null;
    industry?: string | null;
}

export interface Job {
    id: number;
    title: string;
    platform: Platform;
    url: string;
    location: string | null;
    experience_range: string | null;
    salary_text: string | null;
    jd_text?: string | null;
    must_have_skills?: string[] | null;
    nice_to_have_skills?: string[] | null;
    match_score: number | null;
    matched_skills?: string[] | null;
    gaps?: string[] | null;
    found_at: string;
    status: Status;
    company: Company;
}

export interface ChangesSummary {
    added?: string[] | null;
    removed?: string[] | null;
    reordered?: string[] | null;
}

export interface ResumeVersion {
    id: number;
    label: string;
    tex_source: string;
    pdf_path: string | null;
    keywords_covered: string[] | null;
    changes_summary: ChangesSummary | null;
    is_base: boolean;
}

export interface EmailRecord {
    id: number;
    from: string | null;
    subject: string | null;
    snippet: string | null;
    received_at: string | null;
    gmail_account: string;
}

export interface StatusEvent {
    id: number;
    from_status: Status | null;
    to_status: Status;
    source: 'manual' | 'gmail' | 'claude';
    evidence: string | null;
    happened_at: string;
    email: EmailRecord | null;
}

export interface InterviewRound {
    id: number;
    round_name: string;
    round_type: RoundType;
    description: string | null;
    typical_duration: string | null;
    source_url: string | null;
}

export interface InterviewQuestion {
    id: number;
    round_type: RoundType | null;
    question: string;
    topic: string | null;
    difficulty: string | null;
    year_reported: number | null;
    source_url: string | null;
    source_name: string | null;
    suggested_answer: string | null;
    answer_based_on: unknown;
    practiced: boolean;
    my_notes: string | null;
}
