<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\Rule;

use Hyn\Tenancy\Contracts\Repositories\HostnameRepository;
use Hyn\Tenancy\Contracts\Repositories\WebsiteRepository;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
//use Hyn\Tenancy\Models\Website;
use App\Website;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        $invalidSubdomains = config('app.invalid_subdomains');
        $validPlans = [ 'MEI', 'PME' ];

        //dd($data);

        return Validator::make($data, [
            // 'account' => [
            //     'required',
            //     'string',
            //     Rule::notIn( $invalidSubdomains ),
            //     'regex:/^[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])$/'
            // ],
            // 'fqdn' => ['required', 'string', 'unique:hostnames'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            // 'product' => ['required', Rule::in( $validPlans )],
            // 'stripePaymentMethod' => ['required', 'string'],
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function create(array $data)
    {

        // Use the Tenancy package command to create the tenant
        $hostname = $this->createTenant(
            $data['fqdn'],
            $data['product'],
            $data['stripePaymentMethod'],
            $data['email']
        );

        // swap the environment over to the hostname
        app( Environment::class )->hostname( $hostname );

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }

    private function createTenant( $fqdn, $product, $paymentMethod, $email )
    {
        // first create the 'website'
        $website = new Website;
        app( WebsiteRepository::class )->create( $website );

        // now associate the 'website' with a hostname
        $hostname = new Hostname;
        $hostname->fqdn = $fqdn;
        app( HostnameRepository::class )->attach( $hostname, $website );

        // it's important to choose the plan_ ID, not prod_ ID
        $plans = [
            'MEI' => 'plan_G8FJ9hIS10b3Ps',
            'PME' => 'plan_G8FKLiEsb2j7wn'
        ];

        // create the subscription
        $website->newSubscription( $product, $plans[$product] )->create( $paymentMethod, [
            'email' => $email
        ]);

        return $hostname;
    }

    public function register(Request $request) {
        // we'll add in our fqdn here
        $data = $request->all();
        if ( isset( $data['account'] ) ) {
            $fqdn = $data['account'] . '.' . config('app.url_base');
            $request->merge(['fqdn'=>$fqdn]);
        }

        // validate with the validator below
        $this->validator($request->all())->validate();

        // new registered user event
        event(new Registered($user = $this->create($request->all())));

        $port = $request->server('SERVER_PORT') == 80 ? ':80' : '';
        return redirect( ( $request->secure() ? 'https://' : 'http://' ) . $fqdn . $port . '/login?success=1' );
    }


    /**
     * Show the application registration form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showRegistrationForm()
    {
        $website = new Website;
        return view('auth.register', [
            'intent' => $website->createSetupIntent()
        ]);
    }

}
