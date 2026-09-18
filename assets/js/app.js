const API = 'api';

function toast(msg, type = 'default') {
    const t = $('<div class="toast ' + type + '">' + msg + '</div>');
    $('body').append(t);
    setTimeout(() => t.fadeOut(300, () => t.remove()), 3000);
}

function confirm(msg, cb) {
    const html = `<div class="modal-overlay" id="confirm-modal">
        <div class="modal">
            <div class="modal-title">Confirmation</div>
            <p class="text-muted">${msg}</p>
            <div class="modal-actions">
                <button class="btn btn-ghost" onclick="$('#confirm-modal').remove()">Annuler</button>
                <button class="btn btn-danger" id="confirm-ok">Confirmer</button>
            </div>
        </div>
    </div>`;
    $('body').append(html);
    $('#confirm-ok').on('click', () => { $('#confirm-modal').remove(); cb(); });
}

function badgeFE(fe) {
    const value = Number(fe);
    if (Number.isNaN(value)) return '<span class="badge">—</span>';
    if (value >= 55) return '<span class="badge badge-normal">' + value + '%</span>';
    if (value >= 40) return '<span class="badge badge-alerte">' + value + '%</span>';
    return '<span class="badge badge-critique">' + value + '%</span>';
}

function fmtDate(d) {
    if (!d) return '—';
    const date = (String(d).includes('T') || String(d).includes(' ')) ? new Date(d) : new Date(d + 'T00:00:00');
    if (isNaN(date)) return d;
    return date.toLocaleDateString('fr-FR');
}

function getStoredUser() {
    try {
        return JSON.parse(sessionStorage.getItem('echo_user') || 'null');
    } catch (e) {
        return null;
    }
}

function getUserRole(user) {
    return String(user?.role || user?.role_name || 'admin').toLowerCase();
}

function getUserDisplayName(user) {
    return [user?.nom, user?.prenom].filter(Boolean).join(' ').trim() || user?.username || 'ADMIN';
}

function syncSessionActor() {
    const user = getStoredUser();
    if (!user) return null;
    sessionStorage.setItem('echo_current_user', getUserDisplayName(user));
    sessionStorage.setItem('echo_current_role', getUserRole(user));
    return user;
}

function getActorPayload() {
    const user = syncSessionActor() || getStoredUser();
    return {
        actor_name: getUserDisplayName(user),
        actor_role: getUserRole(user)
    };
}

function canUser(action) {
    const user = getStoredUser();
    if (!user) return false;
    if (getUserRole(user) === 'admin') return true;
    const permissions = user.permissions || {};
    const map = {
        read: 'can_view_fiches',
        create: 'can_create_fiches',
        update: 'can_update_fiches',
        delete: 'can_delete_fiches',
        manage_users: 'can_manage_users',
        view_logs: 'can_view_logs'
    };
    return !!permissions[map[action]];
}

function apiErrorMessage(xhr, fallback) {
    if (xhr.responseJSON?.error) return xhr.responseJSON.error;
    if (xhr.status === 0) return 'Impossible de contacter le serveur. Vérifiez qu’Apache et PostgreSQL sont démarrés.';
    return fallback || 'Erreur serveur';
}

function requireLogin() {
    if (!sessionStorage.getItem('echo_user')) {
        window.location.href = 'login.html';
        return false;
    }
    syncSessionActor();
    return true;
}

function applyFichePermissions() {
    const user = getStoredUser();
    if (!user) return;

    if (!canUser('create')) {
        $('a[href="fiche.html"], a[href^="fiche.html"]').not('[href*="id="]').hide();
    }
    if (!canUser('manage_users')) {
        $('a[href="users.html"]').hide();
    }
}

function openProfileModal() {
    const user = getStoredUser();
    if (!user) return;

    if ($('#profile-modal').length) {
        $('#profile-modal').remove();
    }

    $('body').append(`
        <div class="modal-overlay" id="profile-modal">
            <div class="modal">
                <div class="modal-title">Modifier mon profil</div>
                <form id="profile-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nom d’utilisateur</label>
                            <input type="text" id="profile-username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nom</label>
                            <input type="text" id="profile-nom" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Prénom</label>
                            <input type="text" id="profile-prenom" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mot de passe actuel</label>
                            <input type="password" id="profile-current-password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" id="profile-new-password" class="form-control" placeholder="Laisser vide pour ne pas changer">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirmer le nouveau mot de passe</label>
                            <input type="password" id="profile-confirm-password" class="form-control">
                        </div>
                    </div>
                    <div class="modal-actions" style="margin-top:16px">
                        <button type="button" class="btn btn-ghost" id="close-profile-modal">Fermer</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    `);

    $('#profile-username').val(user.username || '');
    $('#profile-nom').val(user.nom || '');
    $('#profile-prenom').val(user.prenom || '');

    $('#close-profile-modal').on('click', function() {
        $('#profile-modal').remove();
    });

    $('#profile-form').on('submit', function(e) {
        e.preventDefault();
        const username = $('#profile-username').val().trim();
        const nom = $('#profile-nom').val().trim();
        const prenom = $('#profile-prenom').val().trim();
        const currentPassword = $('#profile-current-password').val();
        const newPassword = $('#profile-new-password').val();
        const confirmPassword = $('#profile-confirm-password').val();

        if (!username || !nom || !prenom) {
            alert('Tous les champs obligatoires doivent être renseignés.');
            return;
        }
        if (!currentPassword) {
            alert('Le mot de passe actuel est requis.');
            return;
        }
        if (newPassword && newPassword !== confirmPassword) {
            alert('La confirmation du nouveau mot de passe ne correspond pas.');
            return;
        }

        const payload = {
            username,
            nom,
            prenom,
            current_password: currentPassword,
            mot_de_passe: newPassword || '',
            actor_id: Number(user.id),
            ...getActorPayload()
        };

        $.ajax({
            url: API + '/users.php?id=' + user.id,
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function() {
                const updatedUser = { ...user, username, nom, prenom };
                sessionStorage.setItem('echo_user', JSON.stringify(updatedUser));
                syncSessionActor();
                $('#profile-modal').remove();
                toast('Profil mis à jour', 'success');
            },
            error: function(xhr) {
                alert(apiErrorMessage(xhr, 'Erreur lors de la mise à jour du profil'));
            }
        });
    });
}

function ensureProfileLink() {
    const sidebar = $('.sidebar');
    if (!sidebar.length || $('#profile-btn').length) return;

    const user = getStoredUser();
    if (!user) return;

    const logo = sidebar.find('.sidebar-logo');
    if (!logo.length) return;

    const btn = $('<button id="profile-btn" class="profile-btn" aria-label="Mon profil"><i class="fa-solid fa-circle-user"></i></button>');
    const menu = $(
        '<div id="profile-menu" class="profile-menu" style="display:none">' +
            '<div class="profile-menu-user">' + escHtml(getUserDisplayName(user)) + ' · ' + escHtml(getUserRole(user).toUpperCase()) + '</div>' +
            '<a href="#" id="profile-menu-open" class="profile-menu-item"><i class="fa-solid fa-user-pen"></i> Mon profil</a>' +
            '<a href="#" id="profile-menu-logout" class="profile-menu-item"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>' +
        '</div>'
    );

    logo.css({ display: 'flex', alignItems: 'center', justifyContent: 'space-between' });
    logo.append(btn);
    logo.append(menu);

    btn.on('click', function(e) {
        e.stopPropagation();
        $('#profile-menu').toggle();
    });

    $(document).on('click', function() {
        $('#profile-menu').hide();
    });

    $('#profile-menu-open').on('click', function(e) {
        e.preventDefault();
        $('#profile-menu').hide();
        openProfileModal();
    });

    $('#profile-menu-logout').on('click', function(e) {
        e.preventDefault();
        sessionStorage.removeItem('echo_user');
        sessionStorage.removeItem('echo_current_user');
        sessionStorage.removeItem('echo_current_role');
        window.location.href = 'login.html';
    });
}

function escHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function setupMobileNav() {
    const path = window.location.pathname;
    const isTestDir = path.includes('/tests/');
    const prefix = isTestDir ? '../' : '';

    // Ne pas insérer si la structure mobile existe déjà
    if ($('#mobile-nav-drawer').length) return;

    const header = $(`
        <header class="mobile-header no-print">
            <div class="mobile-logo">Echo<span>Cardio</span></div>
            <button class="menu-toggle" id="mobile-menu-btn" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
        </header>
    `);
    
    const drawer = $(`
        <div class="mobile-nav-drawer" id="mobile-nav-drawer">
            <div class="drawer-header">
                <div class="sidebar-logo">Echo<span>Cardio</span></div>
                <button class="close-btn" id="mobile-menu-close"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="drawer-user-info" style="padding: 12px 20px; border-bottom: 1px solid var(--border); margin-bottom: 12px; font-size: 12px; color: var(--text-muted);">
                <i class="fa-solid fa-user"></i> Connecté : <strong style="color: var(--text); display: block;" class="drawer-username-display">...</strong>
            </div>
            <a href="${prefix}dashboard.html" class="nav-item"><i class="fa-solid fa-chart-pie"></i> Tableau de bord</a>
            <a href="${prefix}liste.html" class="nav-item"><i class="fa-solid fa-folder-open"></i> Liste des fiches</a>
            <a href="${prefix}patients.html" class="nav-item"><i class="fa-solid fa-user-injured"></i> Patients</a>
            <a href="${prefix}fiche.html" class="nav-item"><i class="fa-solid fa-file-medical"></i> Nouvelle fiche</a>
            <a href="${prefix}users.html" class="nav-item"><i class="fa-solid fa-users"></i> Utilisateurs</a>
            <a href="${prefix}tests/index.html" class="nav-item"><i class="fa-solid fa-vial"></i> Tests</a>
            
            <div style="margin-top: auto; border-top: 1px solid var(--border); padding-top: 12px;">
                <a href="#" id="mobile-profile-btn" class="nav-item"><i class="fa-solid fa-user-pen"></i> Modifier mon profil</a>
                <a href="#" id="mobile-logout-btn" class="nav-item" style="color: var(--danger);"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
            </div>
        </div>
    `);

    $('body').prepend(header).append(drawer);

    const user = getStoredUser();
    if (user) {
        drawer.find('.drawer-username-display').text(getUserDisplayName(user) + ' · ' + getUserRole(user).toUpperCase());
    }

    const filename = path.split('/').pop();
    drawer.find('.nav-item').each(function() {
        const href = $(this).attr('href');
        if (href && (href === filename || href.endsWith(filename))) {
            $(this).addClass('active');
        }
    });

    if (!canUser('create')) {
        drawer.find(`a[href*="fiche.html"]`).hide();
    }
    if (!canUser('manage_users')) {
        drawer.find(`a[href*="users.html"]`).hide();
    }

    $('#mobile-menu-btn').on('click', function(e) {
        e.stopPropagation();
        $('#mobile-nav-drawer').addClass('open');
    });

    $('#mobile-menu-close, #mobile-nav-drawer .nav-item').on('click', function() {
        $('#mobile-nav-drawer').removeClass('open');
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#mobile-nav-drawer').length) {
            $('#mobile-nav-drawer').removeClass('open');
        }
    });

    drawer.find('#mobile-profile-btn').on('click', function(e) {
        e.preventDefault();
        $('#mobile-nav-drawer').removeClass('open');
        openProfileModal();
    });

    drawer.find('#mobile-logout-btn').on('click', function(e) {
        e.preventDefault();
        sessionStorage.removeItem('echo_user');
        sessionStorage.removeItem('echo_current_user');
        sessionStorage.removeItem('echo_current_role');
        window.location.href = prefix + 'login.html';
    });
}

$(function() {
    const path = window.location.pathname.split('/').pop();
    const publicPages = ['login.html', 'index.html', ''];

    if (!publicPages.includes(path)) {
        if (requireLogin()) {
            applyFichePermissions();
            setupMobileNav();
        }
    }

    $('.nav-item').each(function() {
        if ($(this).attr('href') === path) $(this).addClass('active');
    });

    if (!publicPages.includes(path)) {
        ensureProfileLink();
    }
});
