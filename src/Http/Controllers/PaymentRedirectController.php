<?php

namespace Eduka\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Brunocfalcao\Cerebrus\Cerebrus;
use Brunocfalcao\Tokenizer\Models\Token;
use Eduka\Nereus\Facades\Nereus;

class PaymentRedirectController extends Controller
{
    public const THANK_YOU_OK = 'thank-you-ok';

    public const THANK_YOU_ERROR_NO_COURSE = 'thank-you-error-no-course';

    public const THANK_YOU_ERROR_NO_NONCE = 'thank-you-error-no-nonce';

    private Cerebrus $session;

    public function __construct()
    {
        $this->session = new Cerebrus();
    }

    public function thanksForBuying(string $token)
    {
        $course = Nereus::course();

        if (! $course) {
            return view('course::thanks-for-buying')
                   ->with(['message' => self::THANK_YOU_ERROR_NO_COURSE]);
        }

        if (Token::burn($token)) {
            return view('course::thanks-for-buying')
                   ->with(['message' => self::THANK_YOU_ERROR_NO_NONCE]);
        }

        return view('course::thanks-for-buying')
            ->with(['course' => $course]);
    }
}
