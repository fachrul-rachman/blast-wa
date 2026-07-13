import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/app-layout';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <AppLayout title="Dashboard">
                <div className="grid max-w-6xl gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        ['Total campaigns', '0'],
                        ['Scheduled', '0'],
                        ['Processing', '0'],
                        ['Failed', '0'],
                    ].map(([label, value]) => (
                        <div
                            key={label}
                            className="rounded-lg border border-zinc-200 bg-white p-5"
                        >
                            <p className="text-sm text-zinc-600">{label}</p>
                            <p className="mt-3 text-3xl font-semibold">
                                {value}
                            </p>
                        </div>
                    ))}
                </div>
            </AppLayout>
        </>
    );
}
