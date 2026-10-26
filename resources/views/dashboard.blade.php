@extends('layouts.app')

@section('title', 'Overview')
@section('page', 'dashboard')
@section('eyebrow', 'MONDAY · OPERATIONS')
@section('heading', 'Overview')

@section('content')
    <div class="welcome-row">
        <div>
            <h2>Your service at a glance</h2>
            <p class="muted mb-0">A clear view of customers, properties, and field work.</p>
        </div>
        <a href="{{ route('visits') }}" class="btn btn-primary">＋ Schedule a visit</a>
    </div>

    <div class="row g-3 mt-3" id="dashboard-stats">
        <div class="col-sm-6 col-xl-3"><div class="stat-card"><span class="stat-label">Customers</span><strong id="stat-customers">—</strong><span class="stat-foot">In your workspace</span></div></div>
        <div class="col-sm-6 col-xl-3"><div class="stat-card"><span class="stat-label">Properties</span><strong id="stat-properties">—</strong><span class="stat-foot">Service locations</span></div></div>
        <div class="col-sm-6 col-xl-3"><div class="stat-card"><span class="stat-label">Upcoming visits</span><strong id="stat-upcoming">—</strong><span class="stat-foot">Scheduled field work</span></div></div>
        <div class="col-sm-6 col-xl-3"><div class="stat-card"><span class="stat-label">Completed this month</span><strong id="stat-completed">—</strong><span class="stat-foot">Visits completed</span></div></div>
    </div>

    <section class="content-card mt-4">
        <div class="section-heading">
            <div><h2>Upcoming visits</h2><p class="muted mb-0">Your next scheduled field work.</p></div>
            <a class="text-link" href="{{ route('visits') }}">View all <span aria-hidden="true">→</span></a>
        </div>
        <div class="table-responsive">
            <table class="table app-table mb-0">
                <thead><tr><th>Customer / property</th><th>Technician</th><th>Scheduled</th><th>Status</th></tr></thead>
                <tbody id="dashboard-visits"><tr><td colspan="4" class="empty-state">Loading visits…</td></tr></tbody>
            </table>
        </div>
    </section>
@endsection
