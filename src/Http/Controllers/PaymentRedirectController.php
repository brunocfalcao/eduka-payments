<?php

namespace Eduka\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Brunocfalcao\Cerebrus\Cerebrus;
use Eduka\Nereus\NereusServiceProvider;

class PaymentRedirectController extends Controller
{
    private Cerebrus $session;

    public function __construct(Cerebrus $session)
    {
        $this->session = $session;
    }

    public function index(string $nonce)
    {
        $course = $this->session->get(NereusServiceProvider::COURSE_SESSION_KEY);
        if(! $course) {
            return redirect('/');
        }

        if (!$this->session->has(NereusServiceProvider::NONCE_KEY)) {
            return redirect()->route('welcome.default');
        }

        if ($this->session->get(NereusServiceProvider::NONCE_KEY) !== $nonce) {
            return redirect()->route('welcome.default');
        }

        $this->session->unset(NereusServiceProvider::NONCE_KEY);

        return view('course::congratulations')
            ->with(['course' => $course]);
    }
}
