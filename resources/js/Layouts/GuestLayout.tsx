import { Wordmark } from '@/Layouts/AuthenticatedLayout';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-canvas px-4 py-8">
            <Wordmark />
            <div className="mt-6 w-full max-w-md rounded-2xl border border-line bg-surface p-6">
                {children}
            </div>
        </div>
    );
}
