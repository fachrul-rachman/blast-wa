import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import {
    CalendarClock,
    Eye,
    Lock,
    RotateCcw,
    Send,
    Trash2,
    Upload,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

import AppLayout from '@/layouts/app-layout';

type ImportHeader = { key: string; label: string };
type ImportSummary = {
    headers?: ImportHeader[];
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    duplicate_rows: number;
    missing_data_rows: number;
    skipped_rows: number;
    send_eligible_rows: number;
    phone_column_key?: string | null;
    name_column_key?: string | null;
};
type Campaign = {
    id: number;
    title: string;
    status: string;
    template_name: string;
    template_language: string;
    template_snapshot: {
        body_text?: string | null;
        body_variables?: string[];
    };
    import_summary?: ImportSummary | null;
    scheduled_at?: string | null;
    started_at?: string | null;
    completed_at?: string | null;
    cancelled_at?: string | null;
};
type Recipient = {
    id: number;
    source_row_number: number;
    name: string | null;
    phone_original: string | null;
    phone_normalized: string | null;
    validation_status: string;
    validation_errors: string[] | null;
    duplicate_group_key: string | null;
    delivery_status: string;
    meta_message_id: string | null;
    failure_code: string | null;
    failure_message: string | null;
};
type DeliverySummary = {
    pending: number;
    queued: number;
    accepted: number;
    sent: number;
    delivered: number;
    read: number;
    failed: number;
    skipped: number;
};
type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type VariableMapping = {
    variable: string;
    source_type: string;
    source_column_key: string | null;
    fixed_value: string | null;
};
type Preview = {
    recipient_id: number;
    name: string | null;
    phone_normalized: string | null;
    resolved_values: Record<string, string>;
    rendered_body: string;
};
type PageProps = {
    campaign: Campaign;
    recipients: Paginator<Recipient>;
    delivery_summary: DeliverySummary;
    variable_mappings: VariableMapping[];
    previews: Preview[];
    errors?: Record<string, string>;
};
type UploadForm = {
    recipient_file: File | null;
    phone_column_key: string;
    name_column_key: string;
};
type MappingInput = {
    variable: string;
    source_type: 'column' | 'fixed';
    source_column_key: string;
    fixed_value: string;
};
type ColumnMappingForm = {
    phone_column_key: string;
    name_column_key: string;
};
type SendForm = {
    consent_confirmed: boolean;
};
type ScheduleForm = {
    scheduled_at: string;
    consent_confirmed: boolean;
};

export default function CampaignsShow({
    campaign,
    recipients,
    delivery_summary,
    variable_mappings,
    previews,
}: PageProps) {
    const { props } = usePage<PageProps>();
    const headers = campaign.import_summary?.headers ?? [];
    const variables = campaign.template_snapshot.body_variables ?? [];
    const mappingByVariable = Object.fromEntries(
        variable_mappings.map((mapping) => [mapping.variable, mapping]),
    );

    const uploadForm = useForm<UploadForm>({
        recipient_file: null,
        phone_column_key: '',
        name_column_key: '',
    });
    const mappingForm = useForm({
        phone_column_key: campaign.import_summary?.phone_column_key ?? '',
        name_column_key: campaign.import_summary?.name_column_key ?? '',
    });
    const variableForm = useForm<{ mappings: MappingInput[] }>({
        mappings: variables.map((variable) => ({
            variable,
            source_type:
                mappingByVariable[variable]?.source_type === 'fixed'
                    ? 'fixed'
                    : 'column',
            source_column_key:
                mappingByVariable[variable]?.source_column_key ?? '',
            fixed_value: mappingByVariable[variable]?.fixed_value ?? '',
        })),
    });
    const sendForm = useForm<SendForm>({
        consent_confirmed: false,
    });
    const scheduleForm = useForm<ScheduleForm>({
        scheduled_at: toDateTimeLocal(campaign.scheduled_at),
        consent_confirmed: false,
    });

    function upload(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        uploadForm.post(`/campaigns/${campaign.id}/import`, {
            forceFormData: true,
        });
    }

    function updateMapping(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        mappingForm.patch(`/campaigns/${campaign.id}/import-mapping`);
    }

    function saveVariableMappings(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        variableForm.patch(`/campaigns/${campaign.id}/variable-mappings`);
    }

    function sendNow(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!window.confirm('Send this campaign now? This cannot be undone.')) {
            return;
        }

        sendForm.post(`/campaigns/${campaign.id}/send`);
    }

    function scheduleCampaign(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (campaign.status === 'scheduled') {
            scheduleForm.patch(`/campaigns/${campaign.id}/schedule`);

            return;
        }

        scheduleForm.post(`/campaigns/${campaign.id}/schedule`);
    }

    function cancelSchedule() {
        if (!window.confirm('Cancel this scheduled campaign?')) {
            return;
        }

        router.delete(`/campaigns/${campaign.id}/schedule`);
    }

    function retryFailed() {
        if (!window.confirm('Retry failed recipients now?')) {
            return;
        }

        router.post(`/campaigns/${campaign.id}/retry-failed`);
    }

    const duplicateGroups = groupDuplicates(recipients.data);
    const isDraft = campaign.status === 'draft';
    const canSend =
        isDraft &&
        Boolean(campaign.import_summary) &&
        (campaign.import_summary?.send_eligible_rows ?? 0) > 0;
    const canSchedule = canSend || campaign.status === 'scheduled';
    const canRetryFailed =
        delivery_summary.failed > 0 &&
        (campaign.status === 'completed' || campaign.status === 'failed');

    return (
        <>
            <Head title={campaign.title} />
            <AppLayout
                title={campaign.title}
                actions={
                    <Link
                        href="/campaigns"
                        className="inline-flex h-10 items-center rounded-md border border-zinc-300 px-3 text-sm font-medium hover:bg-zinc-100"
                    >
                        Back
                    </Link>
                }
            >
                <div className="grid max-w-6xl gap-6 lg:grid-cols-[1fr_340px]">
                    <div className="space-y-6">
                        {isDraft ? (
                            <UploadPanel
                                campaignId={campaign.id}
                                form={uploadForm}
                                error={
                                    props.errors?.recipient_file ??
                                    props.errors?.campaign
                                }
                                onSubmit={upload}
                            />
                        ) : (
                            <ReadOnlyPanel status={campaign.status} />
                        )}
                        {campaign.import_summary && (
                            <>
                                <ImportSummaryCards
                                    summary={campaign.import_summary}
                                />
                                <DeliverySummaryCards
                                    summary={delivery_summary}
                                />
                                {isDraft && (
                                    <ColumnMappingPanel
                                        headers={headers}
                                        form={mappingForm}
                                        error={
                                            props.errors?.phone_column_key ??
                                            props.errors?.campaign
                                        }
                                        onSubmit={updateMapping}
                                    />
                                )}
                                {isDraft && duplicateGroups.length > 0 && (
                                    <DuplicatePanel
                                        campaignId={campaign.id}
                                        groups={duplicateGroups}
                                    />
                                )}
                                {isDraft && variables.length > 0 && (
                                    <VariableMappingPanel
                                        headers={headers}
                                        form={variableForm}
                                        error={
                                            props.errors?.mappings ??
                                            props.errors?.campaign
                                        }
                                        onSubmit={saveVariableMappings}
                                    />
                                )}
                                {previews.length > 0 && (
                                    <PreviewPanel previews={previews} />
                                )}
                                {canSend && (
                                    <SendPanel
                                        campaign={campaign}
                                        form={sendForm}
                                        consentError={
                                            props.errors?.consent_confirmed
                                        }
                                        sendError={props.errors?.send}
                                        onSubmit={sendNow}
                                    />
                                )}
                                {canSchedule && (
                                    <SchedulePanel
                                        campaign={campaign}
                                        form={scheduleForm}
                                        consentError={
                                            props.errors?.consent_confirmed
                                        }
                                        scheduleError={props.errors?.schedule}
                                        scheduledAtError={
                                            props.errors?.scheduled_at
                                        }
                                        onSubmit={scheduleCampaign}
                                        onCancel={cancelSchedule}
                                    />
                                )}
                                {canRetryFailed && (
                                    <RetryFailedPanel
                                        failedCount={delivery_summary.failed}
                                        error={props.errors?.retry}
                                        onRetry={retryFailed}
                                    />
                                )}
                                <RecipientTable recipients={recipients.data} />
                                <Pagination paginator={recipients} />
                            </>
                        )}
                    </div>
                    <aside className="space-y-6">
                        <InfoPanel campaign={campaign} />
                        <TemplatePanel campaign={campaign} />
                    </aside>
                </div>
            </AppLayout>
        </>
    );
}

function ReadOnlyPanel({ status }: { status: string }) {
    return (
        <section className="rounded-lg border border-zinc-200 bg-white p-5">
            <div className="flex items-center gap-2">
                <Lock size={18} className="text-zinc-600" />
                <h2 className="text-base font-semibold">Campaign Locked</h2>
            </div>
            <p className="mt-2 text-sm text-zinc-600">
                This campaign is {status}. Recipient data and mappings are now
                read-only.
            </p>
        </section>
    );
}

function UploadPanel({
    form,
    error,
    onSubmit,
}: {
    campaignId: number;
    form: InertiaFormProps<UploadForm>;
    error?: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
    return (
        <section className="rounded-lg border border-zinc-200 bg-white p-5">
            <h2 className="text-base font-semibold">Upload Recipients</h2>
            <p className="mt-1 text-sm text-zinc-600">
                CSV or XLSX, maximum 5 MB, up to 10,000 rows, one XLSX sheet.
            </p>
            <form
                onSubmit={onSubmit}
                className="mt-5 grid gap-4 lg:grid-cols-[1fr_180px]"
            >
                <input
                    type="file"
                    accept=".csv,.xlsx"
                    onChange={(event) =>
                        form.setData(
                            'recipient_file',
                            event.target.files?.[0] ?? null,
                        )
                    }
                    className="block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm"
                />
                <button
                    type="submit"
                    disabled={form.processing}
                    className="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                >
                    <Upload size={16} />
                    <span>Upload</span>
                </button>
            </form>
            {error && <p className="mt-3 text-sm text-red-700">{error}</p>}
        </section>
    );
}

function ImportSummaryCards({ summary }: { summary: ImportSummary }) {
    const items = [
        ['Total', summary.total_rows],
        ['Valid unique', summary.valid_rows],
        ['Invalid', summary.invalid_rows],
        ['Duplicates', summary.duplicate_rows],
        ['Missing data', summary.missing_data_rows],
        ['Send eligible', summary.send_eligible_rows],
    ];

    return (
        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {items.map(([label, value]) => (
                <div
                    key={label}
                    className="rounded-lg border border-zinc-200 bg-white p-4"
                >
                    <p className="text-sm text-zinc-600">{label}</p>
                    <p className="mt-2 text-2xl font-semibold">{value}</p>
                </div>
            ))}
        </section>
    );
}

function DeliverySummaryCards({ summary }: { summary: DeliverySummary }) {
    const items = [
        ['Queued', summary.queued],
        ['Accepted', summary.accepted],
        ['Sent', summary.sent],
        ['Delivered', summary.delivered],
        ['Read', summary.read],
        ['Failed', summary.failed],
        ['Skipped', summary.skipped],
        ['Pending', summary.pending],
    ];

    return (
        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {items.map(([label, value]) => (
                <div
                    key={label}
                    className="rounded-lg border border-zinc-200 bg-white p-4"
                >
                    <p className="text-sm text-zinc-600">{label}</p>
                    <p className="mt-2 text-2xl font-semibold">{value}</p>
                </div>
            ))}
        </section>
    );
}

function ColumnMappingPanel({
    headers,
    form,
    error,
    onSubmit,
}: {
    headers: ImportHeader[];
    form: InertiaFormProps<ColumnMappingForm>;
    error?: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
    return (
        <section className="rounded-lg border border-zinc-200 bg-white p-5">
            <h2 className="text-base font-semibold">Column Mapping</h2>
            <form
                onSubmit={onSubmit}
                className="mt-5 grid gap-4 md:grid-cols-[1fr_1fr_auto]"
            >
                <ColumnSelect
                    label="Phone column"
                    value={form.data.phone_column_key}
                    headers={headers}
                    required
                    onChange={(value) =>
                        form.setData('phone_column_key', value)
                    }
                />
                <ColumnSelect
                    label="Name column"
                    value={form.data.name_column_key}
                    headers={headers}
                    onChange={(value) => form.setData('name_column_key', value)}
                />
                <button
                    type="submit"
                    disabled={form.processing}
                    className="self-end rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-100 disabled:opacity-60"
                >
                    Update
                </button>
            </form>
            {error && <p className="mt-3 text-sm text-red-700">{error}</p>}
        </section>
    );
}

function DuplicatePanel({
    campaignId,
    groups,
}: {
    campaignId: number;
    groups: Recipient[][];
}) {
    function chooseWinner(groupKey: string, winnerId: number) {
        router.patch(`/campaigns/${campaignId}/duplicates`, {
            duplicate_group_key: groupKey,
            winner_id: winnerId,
        });
    }

    return (
        <section className="rounded-lg border border-zinc-200 bg-white p-5">
            <h2 className="text-base font-semibold">Duplicate Review</h2>
            <div className="mt-4 space-y-4">
                {groups.map((group, index) => {
                    const groupKey =
                        group[0]?.duplicate_group_key ?? `group-${index}`;

                    return (
                        <div
                            key={groupKey}
                            className="rounded-md border border-zinc-200 p-4"
                        >
                            <p className="text-sm font-medium">{groupKey}</p>
                            <div className="mt-3 space-y-2">
                                {group.map((recipient) => (
                                    <label
                                        key={recipient.id}
                                        className="flex items-center justify-between gap-4 rounded-md bg-zinc-50 px-3 py-2 text-sm"
                                    >
                                        <span>
                                            Row {recipient.source_row_number} -{' '}
                                            {recipient.name ?? '-'}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                chooseWinner(
                                                    recipient.duplicate_group_key ??
                                                        '',
                                                    recipient.id,
                                                )
                                            }
                                            className="rounded-md border border-zinc-300 px-2 py-1 text-xs font-medium hover:bg-white"
                                        >
                                            Keep
                                        </button>
                                    </label>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function VariableMappingPanel({
    headers,
    form,
    error,
    onSubmit,
}: {
    headers: ImportHeader[];
    form: InertiaFormProps<{ mappings: MappingInput[] }>;
    error?: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
    function update(index: number, patch: Partial<MappingInput>) {
        form.setData(
            'mappings',
            form.data.mappings.map((mapping, currentIndex) =>
                currentIndex === index ? { ...mapping, ...patch } : mapping,
            ),
        );
    }

    return (
        <section className="rounded-lg border border-zinc-200 bg-white p-5">
            <h2 className="text-base font-semibold">Variable Mapping</h2>
            <form onSubmit={onSubmit} className="mt-4 space-y-4">
                {form.data.mappings.map((mapping, index) => (
                    <div
                        key={mapping.variable}
                        className="grid gap-3 rounded-md border border-zinc-200 p-4 md:grid-cols-[180px_140px_1fr]"
                    >
                        <div>
                            <p className="text-sm text-zinc-500">Variable</p>
                            <p className="mt-2 text-sm font-medium">{`{{${mapping.variable}}}`}</p>
                        </div>
                        <label className="block text-sm font-medium">
                            Source
                            <select
                                value={mapping.source_type}
                                onChange={(event) =>
                                    update(index, {
                                        source_type: event.target
                                            .value as MappingInput['source_type'],
                                    })
                                }
                                className="mt-2 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm"
                            >
                                <option value="column">Uploaded Column</option>
                                <option value="fixed">Fixed Value</option>
                            </select>
                        </label>
                        {mapping.source_type === 'column' ? (
                            <ColumnSelect
                                label="Column"
                                value={mapping.source_column_key}
                                headers={headers}
                                required
                                onChange={(value) =>
                                    update(index, { source_column_key: value })
                                }
                            />
                        ) : (
                            <label className="block text-sm font-medium">
                                Fixed Value
                                <input
                                    value={mapping.fixed_value}
                                    onChange={(event) =>
                                        update(index, {
                                            fixed_value: event.target.value,
                                        })
                                    }
                                    className="mt-2 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm"
                                />
                            </label>
                        )}
                    </div>
                ))}
                {error && <p className="text-sm text-red-700">{error}</p>}
                <div className="flex justify-end">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                    >
                        Save Mapping
                    </button>
                </div>
            </form>
        </section>
    );
}

function PreviewPanel({ previews }: { previews: Preview[] }) {
    return (
        <section className="rounded-lg border border-zinc-200 bg-white p-5">
            <div className="mb-4 flex items-center gap-2">
                <Eye size={18} className="text-emerald-700" />
                <h2 className="text-base font-semibold">Preview</h2>
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                {previews.map((preview) => (
                    <div
                        key={preview.recipient_id}
                        className="rounded-md border border-zinc-200 p-4"
                    >
                        <p className="text-sm font-medium">
                            {preview.name ?? '-'} -{' '}
                            {preview.phone_normalized ?? '-'}
                        </p>
                        <pre className="mt-3 rounded-md bg-zinc-50 p-3 text-sm leading-6 whitespace-pre-wrap text-zinc-800">
                            {preview.rendered_body}
                        </pre>
                    </div>
                ))}
            </div>
        </section>
    );
}

function SendPanel({
    campaign,
    form,
    consentError,
    sendError,
    onSubmit,
}: {
    campaign: Campaign;
    form: InertiaFormProps<SendForm>;
    consentError?: string;
    sendError?: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
    const summary = campaign.import_summary;

    return (
        <section className="rounded-lg border border-emerald-200 bg-white p-5">
            <div className="flex items-center gap-2">
                <Send size={18} className="text-emerald-700" />
                <h2 className="text-base font-semibold">Send Now</h2>
            </div>
            <div className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <Info label="Campaign">{campaign.title}</Info>
                <Info label="Template">{campaign.template_name}</Info>
                <Info label="Eligible">{summary?.send_eligible_rows ?? 0}</Info>
            </div>
            <form onSubmit={onSubmit} className="mt-5 space-y-4">
                <label className="flex items-start gap-3 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.consent_confirmed}
                        onChange={(event) =>
                            form.setData(
                                'consent_confirmed',
                                event.target.checked,
                            )
                        }
                        className="mt-1"
                    />
                    <span>
                        I confirm that these recipients have consented to
                        receive WhatsApp messages from the company.
                    </span>
                </label>
                {(consentError || sendError) && (
                    <p className="text-sm text-red-700">
                        {consentError ?? sendError}
                    </p>
                )}
                <button
                    type="submit"
                    disabled={form.processing}
                    className="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                >
                    <Send size={16} />
                    <span>Send eligible recipients</span>
                </button>
            </form>
        </section>
    );
}

function SchedulePanel({
    campaign,
    form,
    consentError,
    scheduleError,
    scheduledAtError,
    onSubmit,
    onCancel,
}: {
    campaign: Campaign;
    form: InertiaFormProps<ScheduleForm>;
    consentError?: string;
    scheduleError?: string;
    scheduledAtError?: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onCancel: () => void;
}) {
    const isScheduled = campaign.status === 'scheduled';
    const summary = campaign.import_summary;

    return (
        <section className="rounded-lg border border-zinc-200 bg-white p-5">
            <div className="flex items-center gap-2">
                <CalendarClock size={18} className="text-emerald-700" />
                <h2 className="text-base font-semibold">
                    {isScheduled ? 'Edit Schedule' : 'Schedule Send'}
                </h2>
            </div>
            <div className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <Info label="Campaign">{campaign.title}</Info>
                <Info label="Template">{campaign.template_name}</Info>
                <Info label="Eligible">{summary?.send_eligible_rows ?? 0}</Info>
            </div>
            <form onSubmit={onSubmit} className="mt-5 space-y-4">
                <label className="block text-sm font-medium">
                    Scheduled time
                    <input
                        type="datetime-local"
                        value={form.data.scheduled_at}
                        onChange={(event) =>
                            form.setData('scheduled_at', event.target.value)
                        }
                        className="mt-2 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm"
                        required
                    />
                </label>
                {!isScheduled && (
                    <label className="flex items-start gap-3 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.consent_confirmed}
                            onChange={(event) =>
                                form.setData(
                                    'consent_confirmed',
                                    event.target.checked,
                                )
                            }
                            className="mt-1"
                        />
                        <span>
                            I confirm that these recipients have consented to
                            receive WhatsApp messages from the company.
                        </span>
                    </label>
                )}
                {(consentError || scheduledAtError || scheduleError) && (
                    <p className="text-sm text-red-700">
                        {consentError ?? scheduledAtError ?? scheduleError}
                    </p>
                )}
                <div className="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                    >
                        <CalendarClock size={16} />
                        <span>
                            {isScheduled ? 'Update schedule' : 'Schedule'}
                        </span>
                    </button>
                    {isScheduled && (
                        <button
                            type="button"
                            onClick={onCancel}
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-red-300 px-4 text-sm font-medium text-red-700 hover:bg-red-50"
                        >
                            <Trash2 size={16} />
                            <span>Cancel schedule</span>
                        </button>
                    )}
                </div>
            </form>
        </section>
    );
}

function RetryFailedPanel({
    failedCount,
    error,
    onRetry,
}: {
    failedCount: number;
    error?: string;
    onRetry: () => void;
}) {
    return (
        <section className="rounded-lg border border-red-200 bg-white p-5">
            <div className="flex items-center gap-2">
                <RotateCcw size={18} className="text-red-700" />
                <h2 className="text-base font-semibold">Retry Failed</h2>
            </div>
            <p className="mt-3 text-sm text-zinc-600">
                {failedCount} failed recipient(s) can be queued again.
            </p>
            {error && <p className="mt-3 text-sm text-red-700">{error}</p>}
            <button
                type="button"
                onClick={onRetry}
                className="mt-4 inline-flex h-10 items-center justify-center gap-2 rounded-md bg-red-700 px-4 text-sm font-medium text-white hover:bg-red-800"
            >
                <RotateCcw size={16} />
                <span>Retry failed recipients</span>
            </button>
        </section>
    );
}

function ColumnSelect({
    label,
    value,
    headers,
    required = false,
    onChange,
}: {
    label: string;
    value: string;
    headers: ImportHeader[];
    required?: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <label className="block text-sm font-medium">
            {label}
            <select
                value={value}
                required={required}
                onChange={(event) => onChange(event.target.value)}
                className="mt-2 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm"
            >
                <option value="">Not selected</option>
                {headers.map((header) => (
                    <option key={header.key} value={header.key}>
                        {header.label}
                    </option>
                ))}
            </select>
        </label>
    );
}

function RecipientTable({ recipients }: { recipients: Recipient[] }) {
    return (
        <section className="overflow-hidden rounded-lg border border-zinc-200 bg-white">
            <table className="w-full text-left text-sm">
                <thead className="border-b border-zinc-200 bg-zinc-100 text-xs text-zinc-600 uppercase">
                    <tr>
                        <th className="px-4 py-3">Row</th>
                        <th className="px-4 py-3">Name</th>
                        <th className="px-4 py-3">Phone</th>
                        <th className="px-4 py-3">Status</th>
                        <th className="px-4 py-3">Delivery</th>
                        <th className="px-4 py-3">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    {recipients.length === 0 ? (
                        <tr>
                            <td
                                colSpan={6}
                                className="px-4 py-8 text-center text-zinc-600"
                            >
                                No recipients found.
                            </td>
                        </tr>
                    ) : (
                        recipients.map((recipient) => (
                            <tr
                                key={recipient.id}
                                className="border-b border-zinc-100 last:border-0"
                            >
                                <td className="px-4 py-4">
                                    {recipient.source_row_number}
                                </td>
                                <td className="px-4 py-4">
                                    {recipient.name ?? '-'}
                                </td>
                                <td className="px-4 py-4">
                                    {recipient.phone_normalized ??
                                        recipient.phone_original ??
                                        '-'}
                                </td>
                                <td className="px-4 py-4 capitalize">
                                    {recipient.validation_status}
                                </td>
                                <td className="px-4 py-4 capitalize">
                                    {recipient.delivery_status}
                                </td>
                                <td className="px-4 py-4 text-zinc-600">
                                    {recipient.validation_errors?.join(', ') ??
                                        recipient.failure_message ??
                                        recipient.duplicate_group_key ??
                                        '-'}
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </section>
    );
}

function Pagination({ paginator }: { paginator: Paginator<unknown> }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between text-sm">
            <button
                type="button"
                disabled={!paginator.prev_page_url}
                onClick={() =>
                    paginator.prev_page_url &&
                    router.get(paginator.prev_page_url, {}, { preserveState: true })
                }
                className="rounded-md border border-zinc-300 px-3 py-2 font-medium hover:bg-zinc-100 disabled:opacity-50"
            >
                Previous
            </button>
            <span className="text-zinc-600">
                Page {paginator.current_page} of {paginator.last_page}
            </span>
            <button
                type="button"
                disabled={!paginator.next_page_url}
                onClick={() =>
                    paginator.next_page_url &&
                    router.get(paginator.next_page_url, {}, { preserveState: true })
                }
                className="rounded-md border border-zinc-300 px-3 py-2 font-medium hover:bg-zinc-100 disabled:opacity-50"
            >
                Next
            </button>
        </div>
    );
}

function InfoPanel({ campaign }: { campaign: Campaign }) {
    return (
        <section className="rounded-lg border border-zinc-200 bg-white p-5">
            <h2 className="mb-4 text-base font-semibold">Campaign</h2>
            <dl className="space-y-3 text-sm">
                <Info label="Status">
                    <span className="capitalize">{campaign.status}</span>
                </Info>
                <Info label="Template">{campaign.template_name}</Info>
                <Info label="Language">{campaign.template_language}</Info>
                {campaign.scheduled_at && (
                    <Info label="Scheduled">
                        {new Date(campaign.scheduled_at).toLocaleString()}
                    </Info>
                )}
            </dl>
        </section>
    );
}

function TemplatePanel({ campaign }: { campaign: Campaign }) {
    return (
        <section className="rounded-lg border border-zinc-200 bg-white p-5">
            <h2 className="text-base font-semibold">Template Body</h2>
            <pre className="mt-4 rounded-md bg-zinc-50 p-4 text-sm leading-6 whitespace-pre-wrap text-zinc-800">
                {campaign.template_snapshot.body_text ?? '-'}
            </pre>
        </section>
    );
}

function Info({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-zinc-500">{label}</dt>
            <dd className="mt-1 font-medium">{children}</dd>
        </div>
    );
}

function groupDuplicates(recipients: Recipient[]): Recipient[][] {
    const groups = new Map<string, Recipient[]>();

    for (const recipient of recipients) {
        if (
            recipient.validation_status !== 'duplicate' ||
            !recipient.duplicate_group_key
        ) {
            continue;
        }

        groups.set(recipient.duplicate_group_key, [
            ...(groups.get(recipient.duplicate_group_key) ?? []),
            recipient,
        ]);
    }

    return Array.from(groups.values());
}

function toDateTimeLocal(value?: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}
