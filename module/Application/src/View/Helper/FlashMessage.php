<?php
/*
 * Helper -- FlashMessenger View Helper
 * chophel@athang.com 
 */
namespace Application\View\Helper;
use Laminas\View\Helper\AbstractHelper;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;

class FlashMessage extends AbstractHelper
{  
    public function __invoke()
    {     
        $flashMessenger = new FlashMessenger();

        $flashMessage = [];
        if ($flashMessenger->hasMessages()) {
            $flashMessage = array_merge($flashMessage, $flashMessenger->getMessages());
        }
        if ($flashMessenger->hasCurrentMessages()) {
            $flashMessage = array_merge($flashMessage, $flashMessenger->getCurrentMessages());
            $flashMessenger->clearCurrentMessages();
        }

        if (count($flashMessage) < 1) {
            return;
        }

        foreach ($flashMessage as $message):
            $title = substr((string) $message, 0, strpos((string) $message, '^'));
            $message = strlen((string) ($title)) > 0 ? substr((string) $message, strpos((string) $message, '^') + 1) : (string) $message;
            $title = strtolower(strlen((string) ($title)) > 0 ? (string) $title : 'error');
            if (!in_array($title, ['success', 'error', 'info', 'warning'], true)) {
                $title = 'info';
            }
            $display_title = ucfirst($title);
            $encodedMessage = json_encode((string) $message);
            $encodedDisplayTitle = json_encode((string) $display_title);
            echo <<<EOF
                <script type="text/javascript">
                    toastr.options = {
                        "closeButton": true,
                        "positionClass": "toast-bottom-right",
                        "onclick": null,
                        "showDuration": "1000",
                        "hideDuration": "1000",
                        "timeOut": "8000",
                        "extendedTimeOut": "1000",
                        "showEasing": "swing",
                        "hideEasing": "linear",
                        "showMethod": "fadeIn",
                        "hideMethod": "fadeOut"
                    }
                    toastr.$title($encodedMessage, $encodedDisplayTitle);
                </script>
            EOF;
        endforeach;
    }
}