@extends('layouts.app')

@section('title', 'Customers')
@section('page', 'customers')
@section('eyebrow', 'DIRECTORY')
@section('heading', 'Customers')

@section('content')
    <div class="welcome-row">
        <div><h2>Customer directory</h2><p class="muted mb-0">Manage customer contact details and service history.</p></div>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#customer-modal" data-action="new-customer">＋ Add customer</button>
    </div>
    <section class="content-card mt-4">
        <div class="table-responsive">
            <table class="table app-table mb-0">
                <thead><tr><th>Customer</th><th>Email</th><th>Phone</th><th>Properties</th><th>Status</th><th></th></tr></thead>
                <tbody id="customers-table"><tr><td colspan="6" class="empty-state">Loading customers…</td></tr></tbody>
            </table>
        </div>
    </section>

    <div class="modal fade" id="customer-modal" tabindex="-1" aria-labelledby="customer-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content app-modal">
                <form id="customer-form">
                    <div class="modal-header"><div><h2 class="modal-title" id="customer-modal-title">Add customer</h2><p class="muted mb-0">Contact details for this account.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="id">
                        <label class="form-label" for="customer-name">Name</label><input class="form-control mb-3" id="customer-name" name="name" required maxlength="255">
                        <label class="form-label" for="customer-email">Email</label><input class="form-control mb-3" id="customer-email" name="email" type="email">
                        <label class="form-label" for="customer-phone">Phone</label><input class="form-control mb-3" id="customer-phone" name="phone" maxlength="40">
                        <label class="form-label" for="customer-status">Status</label><select class="form-select" id="customer-status" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-quiet" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save customer</button></div>
                </form>
            </div>
        </div>
    </div>
@endsection
