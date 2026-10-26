@extends('layouts.app')

@section('title', 'Customer billing')
@section('page', 'billing')
@section('eyebrow', 'CUSTOMER SERVICES')
@section('heading', 'Customer billing')

@section('content')
    <div class="welcome-row">
        <div><h2>Service plans and invoices</h2><p class="muted mb-0">Manage field-service plans, customer subscriptions, and invoices.</p></div>
        <button class="btn btn-primary" type="button" data-action="new-plan">＋ Add plan</button>
    </div>

    <section class="content-card mt-4">
        <div class="section-heading">
            <div><h2>Service plans</h2><p class="muted mb-0">Manage the plans available for customer subscriptions.</p></div>
        </div>
        <div class="table-responsive">
            <table class="table app-table mb-0">
                <thead><tr><th>Plan</th><th>Price</th><th>Billing cycle</th><th>Status</th><th></th></tr></thead>
                <tbody id="plans-table"><tr><td colspan="5" class="empty-state">Loading service plans…</td></tr></tbody>
            </table>
        </div>
    </section>

    <section class="content-card mt-4">
        <div class="section-heading">
            <div><h2>Customer service subscriptions</h2><p class="muted mb-0">A customer's active plan covers completed visits; those visit invoices are issued at zero value.</p></div>
        </div>
        <div class="table-responsive">
            <table class="table app-table mb-0">
                <thead><tr><th>Customer</th><th>Email</th><th>Service plan</th><th>Status</th><th></th></tr></thead>
                <tbody id="customer-subscriptions-table"><tr><td colspan="5" class="empty-state">Loading customer subscriptions…</td></tr></tbody>
            </table>
        </div>
    </section>

    <section class="content-card mt-5">
        <div class="section-heading">
            <div><h2>Customer invoice history</h2><p class="muted mb-0">Invoices for completed work performed by your technicians.</p></div>
        </div>
        <div class="table-responsive">
            <table class="table app-table mb-0">
                <thead><tr><th>Invoice</th><th>Customer</th><th>Service</th><th>Issued</th><th>Due date</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody id="invoices-table"><tr><td colspan="8" class="empty-state">Loading invoices…</td></tr></tbody>
            </table>
        </div>
    </section>

    <div class="modal fade" id="customer-subscription-modal" tabindex="-1" aria-labelledby="customer-subscription-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content app-modal">
                <form id="customer-subscription-form">
                    <div class="modal-header"><div><h2 class="modal-title" id="customer-subscription-title">Assign service plan</h2><p class="muted mb-0">Add a field-service subscription to a customer.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <label class="form-label" for="subscription-customer">Customer</label><select class="form-select mb-3" id="subscription-customer" name="customer_id" required></select>
                        <label class="form-label" for="subscription-plan">Service plan</label><select class="form-select" id="subscription-plan" name="plan_id" required></select>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-quiet" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Assign plan</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="plan-modal" tabindex="-1" aria-labelledby="plan-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content app-modal">
                <form id="plan-form">
                    <div class="modal-header"><div><h2 class="modal-title" id="plan-modal-title">Add service plan</h2><p class="muted mb-0">Define the recurring service plan offered to customers.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="id">
                        <div class="row g-3">
                            <div class="col-md-8"><label class="form-label" for="plan-name">Plan name</label><input class="form-control" id="plan-name" name="name" maxlength="255" required></div>
                            <div class="col-md-4"><label class="form-label" for="plan-code">Code</label><input class="form-control" id="plan-code" name="code" maxlength="255" required></div>
                            <div class="col-md-6"><label class="form-label" for="plan-price">Price</label><input class="form-control" id="plan-price" name="price" type="number" min="0" max="99999999.99" step="0.01" required></div>
                            <div class="col-md-6"><label class="form-label" for="plan-currency">Currency</label><input class="form-control text-uppercase" id="plan-currency" name="currency" value="USD" minlength="3" maxlength="3" pattern="[A-Z]{3}" required></div>
                            <div class="col-md-6"><label class="form-label" for="plan-interval">Billing interval</label><select class="form-select" id="plan-interval" name="billing_interval" required><option value="day">Day</option><option value="week">Week</option><option value="month" selected>Month</option><option value="year">Year</option></select></div>
                            <div class="col-md-6"><label class="form-label" for="plan-interval-count">Interval count</label><input class="form-control" id="plan-interval-count" name="interval_count" type="number" min="1" max="65535" value="1" required></div>
                            <div class="col-12"><label class="form-label" for="plan-description">Description</label><textarea class="form-control" id="plan-description" name="description" rows="2" maxlength="5000"></textarea></div>
                            <div class="col-12"><label class="form-label" for="plan-features">Features</label><textarea class="form-control" id="plan-features" name="features" rows="3" placeholder="One feature per line"></textarea></div>
                            <div class="col-12"><div class="form-check"><input class="form-check-input" id="plan-is-active" name="is_active" type="checkbox" checked><label class="form-check-label" for="plan-is-active">Available for new customer subscriptions</label></div></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-quiet" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save plan</button></div>
                </form>
            </div>
        </div>
    </div>
@endsection
