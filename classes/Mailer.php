<?php
/**
 * ============================================
 * CLASSE MAILER
 * ============================================
 * Gestion de l'envoi des emails
 */

namespace App;

class Mailer
{
    /**
     * Récupérer l'email admin (priorité BDD, fallback constante)
     */
    private static function getAdminEmail(): string
    {
        static $adminEmail = null;
        
        if ($adminEmail === null) {
            try {
                $settings = new Settings();
                $dbEmail = $settings->get('admin_email');
                $adminEmail = !empty($dbEmail) ? $dbEmail : ADMIN_EMAIL;
            } catch (\Exception $e) {
                $adminEmail = ADMIN_EMAIL;
            }
        }
        
        return $adminEmail;
    }
    
    /**
     * Récupérer le nom admin (priorité BDD, fallback constante)
     */
    private static function getAdminName(): string
    {
        static $adminName = null;
        
        if ($adminName === null) {
            try {
                $settings = new Settings();
                $firstName = $settings->get('admin_name');
                $lastName = $settings->get('admin_lastname');
                $full = trim(($firstName ?: '') . ' ' . ($lastName ?: ''));
                $adminName = !empty($full) ? $full : ADMIN_NAME;
            } catch (\Exception $e) {
                $adminName = ADMIN_NAME;
            }
        }
        
        return $adminName;
    }
    
    /**
     * Envoyer un email
     */
    public static function send(string $to, string $subject, string $message): bool
    {
        if (!EMAIL_ENABLED) {
            return true;
        }
        
        $headers = [
            'From' => EMAIL_FROM_NAME . ' <' . EMAIL_FROM . '>',
            'Reply-To' => EMAIL_FROM,
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-Type' => 'text/plain; charset=UTF-8',
            'MIME-Version' => '1.0'
        ];
        
        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= "$key: $value\r\n";
        }
        
        $result = mail($to, $subject, $message, $headerString);
        
        // Log pour debug (à supprimer en production)
        error_log("[Mailer] Envoi vers: $to | Sujet: $subject | Résultat: " . ($result ? 'OK' : 'ECHEC'));
        
        return $result;
    }
    
    /**
     * Générer le lien de gestion du RDV
     */
    private static function getManageLink(array $booking): string
    {
        $token = $booking['manage_token'] ?? '';
        if (empty($token)) {
            return '';
        }
        return BASE_URL . "modules/booking/manage.php?token=" . $token;
    }
    
    /**
     * Ajouter le bloc de gestion à un email
     */
    private static function addManageBlock(string $message, array $booking): string
    {
        $manageLink = self::getManageLink($booking);
        if (empty($manageLink)) {
            error_log("[Mailer] addManageBlock: lien vide - manage_token=" . ($booking['manage_token'] ?? 'NULL'));
            return $message;
        }
        
        error_log("[Mailer] addManageBlock: lien généré = $manageLink");

        // Lien vers l'espace client unifié (§7.b) : même token, espace.php
        // résout l'email → agrège séances + forfaits + historique.
        $espaceLink = BASE_URL . "modules/booking/espace.php?token=" . ($booking['manage_token'] ?? '');

        $message .= "\n---\n";
        $message .= "Gérer ce rendez-vous (modifier, déplacer ou annuler) :\n";
        $message .= $manageLink . "\n\n";
        $message .= "Voir tout mon espace (mes séances et forfaits) :\n";
        $message .= $espaceLink . "\n";

        return $message;
    }
    
    /**
     * Notifier une nouvelle réservation
     */
    public static function notifyNewBooking(array $booking): void
    {
        $slotDate = Helpers::formatDateFr($booking['slot_date']);
        $slotTime = Helpers::formatTimeSlot($booking['slot_time_start'], $booking['slot_time_end']);
        
        // Email au visiteur
        $subject = "Demande de RDV en attente - " . SITE_NAME;
        $message = self::buildVisitorNewBookingEmail($booking, $slotDate, $slotTime);
        self::send($booking['visitor_email'], $subject, $message);
        
        // Petit délai pour éviter le rate limiting OVH
        usleep(500000); // 0.5 seconde
        
        // Email à l'admin
        $adminSubject = "🟡 Nouveau RDV - {$booking['visitor_name']}";
        $adminMessage = self::buildAdminNewBookingEmail($booking, $slotDate, $slotTime);
        self::send(self::getAdminEmail(), $adminSubject, $adminMessage);
    }
    
    /**
     * Notifier la confirmation d'une réservation
     */
    public static function notifyConfirmation(array $booking): void
    {
        $slotDate = Helpers::formatDateFr($booking['slot_date']);
        $slotTime = Helpers::formatTimeSlot($booking['slot_time_start'], $booking['slot_time_end']);
        
        $subject = "✅ RDV Confirmé - " . SITE_NAME;
        
        $message = "Bonjour {$booking['visitor_name']},\n\n";
        $message .= "Votre rendez-vous a été confirmé !\n\n";
        $message .= "📅 Date : $slotDate\n";
        $message .= "🕐 Horaire : $slotTime\n\n";
        $message .= "À bientôt,\n" . self::getAdminName();
        
        // Ajouter le lien de gestion
        $message = self::addManageBlock($message, $booking);
        
        self::send($booking['visitor_email'], $subject, $message);
    }
    
    /**
     * Notifier l'annulation d'une réservation (par l'admin)
     */
    public static function notifyCancellation(array $booking): void
    {
        $slotDate = Helpers::formatDateFr($booking['slot_date']);
        $slotTime = Helpers::formatTimeSlot($booking['slot_time_start'], $booking['slot_time_end']);
        
        $subject = "❌ RDV Annulé - " . SITE_NAME;
        
        $message = "Bonjour {$booking['visitor_name']},\n\n";
        $message .= "Nous sommes désolés, votre demande de rendez-vous n'a pas pu être acceptée.\n\n";
        $message .= "📅 Date demandée : $slotDate\n";
        $message .= "🕐 Horaire demandé : $slotTime\n\n";
        $message .= "N'hésitez pas à faire une nouvelle demande sur un autre créneau :\n";
        $message .= BASE_URL . "modules/booking/\n\n";
        $message .= "Cordialement,\n" . self::getAdminName();

        self::send($booking['visitor_email'], $subject, $message);
    }

    /**
     * Notifier le déplacement d'une réservation (par l'admin)
     */
    public static function notifyReschedule(array $oldBooking, array $newBooking): void
    {
        $oldDate = Helpers::formatDateFr($oldBooking['slot_date']);
        $oldTime = Helpers::formatTimeSlot($oldBooking['slot_time_start'], $oldBooking['slot_time_end']);
        $newDate = Helpers::formatDateFr($newBooking['slot_date']);
        $newTime = Helpers::formatTimeSlot($newBooking['slot_time_start'], $newBooking['slot_time_end']);
        
        $subject = "📅 RDV Déplacé - " . SITE_NAME;
        
        $message = "Bonjour {$oldBooking['visitor_name']},\n\n";
        $message .= "Votre rendez-vous a été déplacé.\n\n";
        $message .= "❌ Ancien créneau :\n";
        $message .= "   📅 Date : $oldDate\n";
        $message .= "   🕐 Horaire : $oldTime\n\n";
        $message .= "✅ Nouveau créneau :\n";
        $message .= "   📅 Date : $newDate\n";
        $message .= "   🕐 Horaire : $newTime\n\n";
        $message .= "Cordialement,\n" . self::getAdminName();
        
        // Ajouter le lien de gestion
        $message = self::addManageBlock($message, $newBooking);
        
        self::send($oldBooking['visitor_email'], $subject, $message);
    }
    
    /**
     * Notifier une demande de déplacement par le client (à l'admin)
     */
    public static function notifyClientRescheduleRequest(array $booking, string $oldDate, string $oldTime): void
    {
        $newDate = Helpers::formatDateFr($booking['slot_date']);
        $newTime = Helpers::formatTimeSlot($booking['slot_time_start'], $booking['slot_time_end']);
        
        // Email de confirmation au client d'abord
        $clientSubject = "Demande de deplacement enregistree - " . SITE_NAME;
        $clientMessage = "Bonjour {$booking['visitor_name']},\n\n";
        $clientMessage .= "Votre demande de déplacement a bien été enregistrée.\n\n";
        $clientMessage .= "Ancien créneau :\n";
        $clientMessage .= "   Date : $oldDate\n";
        $clientMessage .= "   Horaire : $oldTime\n\n";
        $clientMessage .= "Nouveau créneau demandé :\n";
        $clientMessage .= "   Date : $newDate\n";
        $clientMessage .= "   Horaire : $newTime\n\n";
        $clientMessage .= "Votre demande est en attente de validation. ";
        $clientMessage .= "Vous recevrez un email de confirmation.\n\n";
        $clientMessage .= "Cordialement,\n" . self::getAdminName();
        
        // Ajouter le lien de gestion
        $clientMessage = self::addManageBlock($clientMessage, $booking);
        
        self::send($booking['visitor_email'], $clientSubject, $clientMessage);
        
        // Petit délai pour éviter le rate limiting OVH
        usleep(500000); // 0.5 seconde
        
        // Email à l'admin
        $subject = "🟡 Déplacement RDV - {$booking['visitor_name']}";
        
        $message = "Le client a demandé à déplacer son rendez-vous :\n\n";
        $message .= "Nom : {$booking['visitor_name']}\n";
        $message .= "Email : {$booking['visitor_email']}\n\n";
        $message .= "Ancien créneau :\n";
        $message .= "   Date : $oldDate\n";
        $message .= "   Horaire : $oldTime\n\n";
        $message .= "Nouveau créneau demandé :\n";
        $message .= "   Date : $newDate\n";
        $message .= "   Horaire : $newTime\n\n";
        $message .= "Ce rendez-vous est en attente de votre validation.\n\n";
        $message .= "---\n";
        $message .= "Connectez-vous pour valider ou refuser :\n";
        $message .= BASE_URL . "admin/";
        
        self::send(self::getAdminEmail(), $subject, $message);
    }
    
    /**
     * Notifier une annulation par le client (à l'admin)
     */
    public static function notifyClientCancellation(array $booking): void
    {
        $slotDate = Helpers::formatDateFr($booking['slot_date']);
        $slotTime = Helpers::formatTimeSlot($booking['slot_time_start'], $booking['slot_time_end']);
        
        // Email de confirmation au client d'abord
        $clientSubject = "Annulation confirmee - " . SITE_NAME;
        $clientMessage = "Bonjour {$booking['visitor_name']},\n\n";
        $clientMessage .= "Votre rendez-vous a bien été annulé.\n\n";
        $clientMessage .= "Date : $slotDate\n";
        $clientMessage .= "Horaire : $slotTime\n\n";
        $clientMessage .= "Si vous souhaitez prendre un nouveau rendez-vous, vous pouvez le faire ici :\n";
        $clientMessage .= BASE_URL . "modules/booking/\n\n";
        $clientMessage .= "Cordialement,\n" . self::getAdminName();
        
        self::send($booking['visitor_email'], $clientSubject, $clientMessage);
        
        // Petit délai pour éviter le rate limiting OVH
        usleep(500000); // 0.5 seconde
        
        // Email à l'admin
        $subject = "❌ Annulation RDV - {$booking['visitor_name']}";
        
        $message = "Le client a annulé son rendez-vous :\n\n";
        $message .= "Nom : {$booking['visitor_name']}\n";
        $message .= "Email : {$booking['visitor_email']}\n\n";
        $message .= "Date : $slotDate\n";
        $message .= "Horaire : $slotTime\n\n";
        $message .= "Le créneau est à nouveau disponible.";
        
        self::send(self::getAdminEmail(), $subject, $message);
    }

    /**
     * Confirmation d'ACHAT d'un forfait (§7) — au client, avec le lien de son
     * espace forfait (pack.php?token=…) d'où il réservera ses séances.
     */
    public static function notifyPackagePurchase(array $purchase): void
    {
        $link = BASE_URL . 'modules/booking/espace.php?token=' . $purchase['manage_token'];
        $subject = "🎟️ Votre forfait est actif - " . SITE_NAME;
        $message  = "Bonjour {$purchase['client_name']},\n\n";
        $message .= "Votre forfait « {$purchase['package_name']} » est actif : ";
        $message .= "{$purchase['credits_total']} séances de « {$purchase['service_name']} ».\n\n";
        $message .= "Réservez vos séances (une séance = un jeton) depuis votre espace forfait :\n";
        $message .= "$link\n\n";
        if (!empty($purchase['expires_at'])) {
            $message .= "Valable jusqu'au " . Helpers::formatDateFr(substr($purchase['expires_at'], 0, 10)) . ".\n\n";
        }
        $message .= "À bientôt,\n" . self::getAdminName();
        self::send($purchase['client_email'], $subject, $message);
    }

    /**
     * Confirmation d'une SÉANCE réservée avec un jeton de forfait (§7).
     */
    public static function notifyPackageSession(array $purchase, array $booking): void
    {
        $slotDate  = Helpers::formatDateFr($booking['slot_date']);
        $slotTime  = Helpers::formatTimeSlot($booking['slot_time_start'], $booking['slot_time_end']);
        $link      = BASE_URL . 'modules/booking/pack.php?token=' . $purchase['manage_token'];
        $remaining = max(0, (int) $purchase['credits_total'] - (int) $purchase['credits_used']);
        $subject = "✅ Séance réservée - " . SITE_NAME;
        $message  = "Bonjour {$purchase['client_name']},\n\n";
        $message .= "Votre séance (forfait « {$purchase['package_name']} ») est réservée :\n\n";
        $message .= "📅 Date : $slotDate\n";
        $message .= "🕐 Horaire : $slotTime\n\n";
        $message .= "Jetons restants : $remaining.\n";
        $message .= "Votre espace forfait : $link\n\n";
        $message .= "À bientôt,\n" . self::getAdminName();
        self::send($purchase['client_email'], $subject, $message);
    }

    /**
     * Construire l'email visiteur pour nouvelle réservation
     */
    private static function buildVisitorNewBookingEmail(array $booking, string $date, string $time): string
    {
        $message = "Bonjour {$booking['visitor_name']},\n\n";
        $message .= "Votre demande de rendez-vous a bien été enregistrée.\n\n";
        $message .= "📅 Date : $date\n";
        $message .= "🕐 Horaire : $time\n\n";
        $message .= "⏳ Votre demande est en attente de validation. ";
        $message .= "Vous recevrez un email de confirmation dès que votre rendez-vous sera validé.\n\n";
        $message .= "Cordialement,\n" . self::getAdminName();
        
        // Ajouter le lien de gestion
        $message = self::addManageBlock($message, $booking);
        
        return $message;
    }
    
    /**
     * Construire l'email admin pour nouvelle réservation
     */
    private static function buildAdminNewBookingEmail(array $booking, string $date, string $time): string
    {
        // v3 : nom récupéré via JOIN dans Booking::getById()/getByToken().
        $serviceName = !empty($booking['service_name']) ? $booking['service_name'] : 'Prestation';
        
        $message = "Nouvelle demande de rendez-vous :\n\n";
        $message .= "👤 Nom : {$booking['visitor_name']}\n";
        $message .= "📧 Email : {$booking['visitor_email']}\n";
        
        if (!empty($booking['visitor_phone'])) {
            $message .= "📞 Tél : {$booking['visitor_phone']}\n";
        }
        if (!empty($booking['visitor_organization'])) {
            $message .= "🏢 Organisation : {$booking['visitor_organization']}\n";
        }
        
        $message .= "\n📅 Date : $date\n";
        $message .= "🕐 Horaire : $time\n";
        $message .= "📋 Service : $serviceName\n";
        
        if (!empty($booking['subject'])) {
            $message .= "📝 Objet : {$booking['subject']}\n";
        }
        if (!empty($booking['message'])) {
            $message .= "\n💬 Message :\n{$booking['message']}\n";
        }
        
        $message .= "\n---\n";
        $message .= "Connectez-vous pour valider ou refuser :\n";
        $message .= BASE_URL . "admin/";
        
        return $message;
    }
}
