@extends('layouts.app')

@section('title', 'Properties')
@section('page', 'properties')
@section('eyebrow', 'SERVICE LOCATIONS')
@section('heading', 'Properties')

@section('content')
    <div class="welcome-row">
        <div><h2>Properties</h2><p class="muted mb-0">Locations and pool details for scheduled service.</p></div>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#property-modal" data-action="new-property">＋ Add property</button>
    </div>
    <section class="content-card mt-4">
        <div class="table-responsive">
            <table class="table app-table mb-0">
                <thead><tr><th>Location</th><th>Customer</th><th>Pool type</th><th>City</th><th></th></tr></thead>
                <tbody id="properties-table"><tr><td colspan="5" class="empty-state">Loading properties…</td></tr></tbody>
            </table>
        </div>
    </section>

    <div class="modal fade" id="property-modal" tabindex="-1" aria-labelledby="property-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content app-modal">
                <form id="property-form">
                    <div class="modal-header"><div><h2 class="modal-title" id="property-modal-title">Add property</h2><p class="muted mb-0">Property location and service details.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="id">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label" for="property-customer">Customer</label><select class="form-select" id="property-customer" name="customer_id" required></select></div>
                            <div class="col-12"><label class="form-label" for="property-name">Property name <span class="muted">(optional)</span></label><input class="form-control" id="property-name" name="name"></div>
                            <div class="col-md-8"><label class="form-label" for="property-address">Address</label><input class="form-control" id="property-address" name="address_line_1" required></div>
                            <div class="col-md-4"><label class="form-label" for="property-address2">Unit / suite</label><input class="form-control" id="property-address2" name="address_line_2"></div>
                            <div class="col-md-5"><label class="form-label" for="property-city">City</label><input class="form-control" id="property-city" name="city" required></div>
                            <div class="col-md-3"><label class="form-label" for="property-state">State</label><input class="form-control" id="property-state" name="state"></div>
                            <div class="col-md-4"><label class="form-label" for="property-postal">Postal code</label><input class="form-control" id="property-postal" name="postal_code"></div>
                            <div class="col-md-4"><label class="form-label" for="property-country">Country code</label><input class="form-control" id="property-country" name="country" value="US" maxlength="2" required></div>
                            <div class="col-md-8"><label class="form-label" for="property-pool">Pool type</label><input class="form-control" id="property-pool" name="pool_type" placeholder="e.g. saltwater"></div>
                            <div class="col-12"><label class="form-label" for="property-access">Access code <span class="muted">(optional)</span></label><input class="form-control" id="property-access" name="access_code" autocomplete="off"><div class="form-text">Encrypted at rest. The code is not shown after saving.</div></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-quiet" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save property</button></div>
                </form>
            </div>
        </div>
    </div>
@endsection
