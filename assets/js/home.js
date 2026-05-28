/**
 * ============================================
 * JS - PAGE D'ACCUEIL (SITE VITRINE)
 * ============================================
 * Le formulaire de contact n'a pas de backend dédié : à l'envoi, il ouvre
 * le client mail de l'utilisateur avec un message pré-rempli (mailto).
 * Pour passer à un envoi serveur, brancher ici un fetch vers un endpoint
 * dédié (ex: api/contact.php) — sans toucher au module réservation.
 */
(function () {
    'use strict';

    var form = document.getElementById('contact-form');
    if (!form) return;
    var DEST = form.dataset.dest || 'contact@focuscoach.fr';

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var name    = (document.getElementById('ct-name').value || '').trim();
        var email   = (document.getElementById('ct-email').value || '').trim();
        var profile = document.getElementById('ct-profile').value || '';
        var message = (document.getElementById('ct-message').value || '').trim();

        if (!name || !email || !message) {
            alert('Merci de renseigner au moins votre nom, votre email et votre message.');
            return;
        }

        var subject = 'Demande de contact — ' + name;
        var lines = [
            'Nom : ' + name,
            'Email : ' + email,
            'Profil : ' + (profile || 'Non précisé'),
            '',
            'Message :',
            message
        ];
        var body = lines.join('\r\n');

        window.location.href = 'mailto:' + DEST +
            '?subject=' + encodeURIComponent(subject) +
            '&body=' + encodeURIComponent(body);
    });
})();
