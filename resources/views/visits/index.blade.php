@extends('layouts.app')

@section('title', 'Visits')
@section('page', 'visits')
@section('eyebrow', 'FIELD OPERATIONS')
@section('heading', 'Visits')

@section('content')
    <div class="welcome-row">
        <div><h2>Visit schedule</h2><p class="muted mb-0">Plan work and follow each service from the field.</p></div>
        <button id="schedule-visit-button" class="btn btn-primary" type="button" data-action="new-visit">＋ Schedule visit</button>
    </div>
    <div class="filter-row mt-4">
        <label class="visually-hidden" for="visit-status-filter">Filter visits by status</label>
        <select class="form-select filter-select" id="visit-status-filter">
            <option value="">All statuses</option>
            <option value="scheduled">Scheduled</option>
            <option value="in_progress">In progress</option>
            <option value="completed">Completed</option>
        </select>
    </div>
    <section class="content-card mt-3">
        <div class="table-responsive">
            <table class="table app-table mb-0">
                <thead><tr><th>Customer / property</th><th>Technician</th><th>Scheduled</th><th>Status</th><th></th></tr></thead>
                <tbody id="visits-table"><tr><td colspan="5" class="empty-state">Loading visits…</td></tr></tbody>
            </table>
        </div>
    </section>

    <div class="modal fade" id="schedule-modal" tabindex="-1" aria-labelledby="schedule-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content app-modal">
                <form id="schedule-form">
                    <div class="modal-header"><div><h2 class="modal-title" id="schedule-modal-title">Schedule a visit</h2><p class="muted mb-0">Assign a technician and property.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <label class="form-label" for="visit-property">Property</label><select class="form-select mb-3" id="visit-property" name="property_id" required></select>
                        <label class="form-label" for="visit-technician">Technician</label><select class="form-select mb-3" id="visit-technician" name="assigned_to"><option value="">Unassigned</option></select>
                        <label class="form-label" for="visit-scheduled">Date and time</label><input class="form-control" id="visit-scheduled" name="scheduled_at" type="datetime-local" required>
                        <label class="form-label mt-3" for="visit-requested-services">Services requested by customer</label><textarea class="form-control" id="visit-requested-services" name="requested_services" rows="3" maxlength="13000" placeholder="One requested service per line"></textarea><div class="form-text">The technician can review these requests before recording the work performed.</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-quiet" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Schedule visit</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="visit-details-modal" tabindex="-1" aria-labelledby="visit-details-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content app-modal">
                <div class="modal-header"><div><h2 class="modal-title" id="visit-details-title">Visit details</h2><p class="muted mb-0" id="visit-details-subtitle">Service report</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body" id="visit-details-content"><div class="empty-state">Loading visit…</div></div>
                <div class="modal-footer"><button type="button" class="btn btn-quiet" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="visit-invoice-modal" tabindex="-1" aria-labelledby="visit-invoice-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content app-modal">
                <form id="visit-invoice-form">
                    <div class="modal-header"><div><h2 class="modal-title" id="visit-invoice-title">Create visit invoice</h2><p class="muted mb-0">Issue an invoice for this completed service.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="visit_id">
                        <label class="form-label" for="visit-invoice-description">Description</label><input class="form-control mb-3" id="visit-invoice-description" name="description" maxlength="255" required>
                        <div class="row g-3">
                            <div class="col-8"><label class="form-label" id="visit-invoice-amount-label" for="visit-invoice-amount">Amount</label><input class="form-control" id="visit-invoice-amount" name="amount" type="number" min="0" max="99999999.99" step="0.01" required></div>
                            <div class="col-4"><label class="form-label" for="visit-invoice-currency">Currency</label><input class="form-control text-uppercase" id="visit-invoice-currency" name="currency" value="USD" minlength="3" maxlength="3" pattern="[A-Z]{3}" required></div>
                        </div>
                        <p class="form-text mt-3" id="visit-invoice-subscription-note">The invoice will be due in 14 days. Payment processing is not enabled.</p>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-quiet" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Issue invoice</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="complete-modal" tabindex="-1" aria-labelledby="complete-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content app-modal">
                <form id="complete-form">
                    <div class="modal-header"><div><h2 class="modal-title" id="complete-modal-title">Complete visit</h2><p class="muted mb-0">Record service results and photos.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="visit_id">
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label" for="visit-chlorine">Chlorine</label><input class="form-control" id="visit-chlorine" name="chlorine_reading" type="number" min="0" max="20" step="0.01"></div>
                            <div class="col-6"><label class="form-label" for="visit-ph">pH</label><input class="form-control" id="visit-ph" name="ph_reading" type="number" min="0" max="14" step="0.01"></div>
                            <div class="col-12"><label class="form-label" for="visit-services">Services performed</label><textarea class="form-control" id="visit-services" name="services_performed" rows="3" placeholder="One service per line"></textarea></div>
                            <div class="col-12"><label class="form-label" for="visit-notes">Technician notes</label><textarea class="form-control" id="visit-notes" name="technician_notes" rows="3"></textarea></div>
                            <div class="col-12"><label class="form-label" for="visit-photos">Photos</label><input class="form-control" id="visit-photos" name="photos" type="file" accept="image/jpeg,image/png,image/webp" multiple><div class="form-text">Up to 5 photos, 5 MB each.</div></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-quiet" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Complete visit</button></div>
                </form>
            </div>
        </div>
    </div>
@endsection
