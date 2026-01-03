<div class="header-actions">
    <h1>Admin Dashboard</h1>
</div>

<div class="grid-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
    
    <!-- Users Tile -->
    <div class="card" style="cursor: pointer;" onclick="window.location.href='/admin/users'">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="background: #e3f2fd; padding: 1rem; border-radius: 50%; color: #1976d2;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
            <h2 style="margin: 0;">Gebruikers</h2>
        </div>
        <p style="color: #666; margin-bottom: 1.5rem;">Beheer accounts, rechten en toegang.</p>
        <a href="/admin/users" class="btn btn-outline" style="width: 100%; text-align: center;">Beheren</a>
    </div>

    <!-- Options Tile -->
    <div class="card" style="cursor: pointer;" onclick="window.location.href='/admin/options'">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="background: #e8f5e9; padding: 1rem; border-radius: 50%; color: #2e7d32;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
            </div>
            <h2 style="margin: 0;">Oefenstof Opties</h2>
        </div>
        <p style="color: #666; margin-bottom: 1.5rem;">Beheer categorieën, tags en instellingen.</p>
        <a href="/admin/options" class="btn btn-outline" style="width: 100%; text-align: center;">Beheren</a>
    </div>

    <!-- Teams Tile -->
    <div class="card" style="cursor: pointer;" onclick="window.location.href='/admin/teams'">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="background: #fff3e0; padding: 1rem; border-radius: 50%; color: #ef6c00;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
            <h2 style="margin: 0;">Teams</h2>
        </div>
        <p style="color: #666; margin-bottom: 1.5rem;">Bekijk en beheer teams van gebruikers.</p>
        <a href="/admin/teams" class="btn btn-outline" style="width: 100%; text-align: center;">Beheren</a>
    </div>

    <!-- System Tile -->
    <div class="card" style="cursor: pointer;" onclick="window.location.href='/admin/system'">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="background: #f5f5f5; padding: 1rem; border-radius: 50%; color: #616161;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            </div>
            <h2 style="margin: 0;">Systeem</h2>
        </div>
        <p style="color: #666; margin-bottom: 1.5rem;">Logs, instellingen en systeemstatus.</p>
        <a href="/admin/system" class="btn btn-outline" style="width: 100%; text-align: center;">Bekijken</a>
    </div>

</div>

