@extends('layouts.app')

@section('title', 'Team')
@section('page', 'team')
@section('eyebrow', 'WORKSPACE')
@section('heading', 'Team')

@section('content')
    <div class="welcome-row">
        <div><h2>Team members</h2><p class="muted mb-0">Manage administrators and technicians in this workspace.</p></div>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#team-modal">＋ Add team member</button>
    </div>
    <section class="content-card mt-4">
        <div class="table-responsive">
            <table class="table app-table mb-0">
                <thead><tr><th>Member</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
                <tbody id="team-table"><tr><td colspan="4" class="empty-state">Loading team…</td></tr></tbody>
            </table>
        </div>
    </section>

    <div class="modal fade" id="team-modal" tabindex="-1" aria-labelledby="team-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content app-modal">
                <form id="team-form">
                    <div class="modal-header"><div><h2 class="modal-title" id="team-modal-title">Add team member</h2><p class="muted mb-0">Create login credentials for this workspace.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <label class="form-label" for="team-name">Name</label><input class="form-control mb-3" id="team-name" name="name" required maxlength="255">
                        <label class="form-label" for="team-email">Email</label><input class="form-control mb-3" id="team-email" name="email" type="email" required>
                        <label class="form-label" for="team-role">Role</label>
                        <select class="form-select mb-3" id="team-role" name="role" required>
                            <option value="technician">Technician</option>
                            <option value="admin">Administrator</option>
                        </select>
                        <label class="form-label" for="team-password">Temporary password</label><input class="form-control mb-3" id="team-password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                        <label class="form-label" for="team-password-confirmation">Confirm password</label><input class="form-control" id="team-password-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                        <p class="form-text mt-2 mb-0">Share the temporary password securely and ask the team member to change it after signing in.</p>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-quiet" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Create account</button></div>
                </form>
            </div>
        </div>
    </div>
@endsection
