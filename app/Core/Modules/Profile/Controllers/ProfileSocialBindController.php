<?php

namespace Flute\Core\Modules\Profile\Controllers;

use DateTime;
use Flute\Core\Database\Entities\SocialNetwork;
use Flute\Core\Database\Entities\UserSocialNetwork;
use Flute\Core\Exceptions\SocialNotFoundException;
use Flute\Core\Exceptions\UserNotFoundException;
use Flute\Core\Support\BaseController;
use Flute\Core\Support\FluteRequest;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProfileSocialBindController extends BaseController
{
    /**
     * Shows the social network binding page.
     */
    public function bindSocial(FluteRequest $fluteRequest, string $provider): Response
    {
        try {
            social()->bindSocialNetwork(user()->getCurrentUser(), ucfirst($provider));

            return $this->socialSuccess();
        } catch (UserNotFoundException $e) {
            return $this->socialError(__('auth.errors.user_not_found'));
        } catch (SocialNotFoundException $e) {
            return $this->socialError(__('auth.errors.social_not_found'));
        } catch (Throwable $e) {
            logs()->error($e);

            if (is_debug()) {
                throw $e;
            }

            return $this->socialError(__('auth.errors.unknown'));
        }
    }

    /**
     * Unbinds the social network from the user's profile.
     */
    public function unbindSocial(FluteRequest $fluteRequest, string $provider): Response
    {
        $socialNetworkEntity = SocialNetwork::findOne(['key' => $provider]);

        if (!$socialNetworkEntity) {
            return redirect()->back()->withErrors(t('profile.errors.social_not_connected'));
        }

        $socialNetwork = UserSocialNetwork::findOne([
            'user_id' => user()->id,
            'socialNetwork_id' => $socialNetworkEntity->id,
        ]);

        $countSocialNetworks = UserSocialNetwork::query()->where(['user_id' => user()->id])->count();

        if (!$socialNetwork) {
            return redirect()->back()->withErrors(t('profile.errors.social_not_connected'));
        }

        if ($socialNetwork->socialNetwork === null) {
            return redirect()->back()->withErrors(t('profile.errors.social_not_connected'));
        }

        if ($countSocialNetworks === 1 && !$socialNetwork->user->password) {
            return redirect()->back()->withErrors(t('profile.errors.social_only_one'));
        }

        $lastLinked = $socialNetwork->linkedAt;
        $now = new DateTime();

        if (
            $socialNetwork->socialNetwork?->cooldownTime > 0 && (
                $lastLinked
                && ( $now->getTimestamp() - $lastLinked->getTimestamp() )
                < $socialNetwork->socialNetwork?->cooldownTime
            )
        ) {
            return redirect()->back()->withErrors(t('profile.errors.social_delay'));
        }

        transaction($socialNetwork, 'delete')->run();

        if ($provider === 'Discord') {
            app()->get(DiscordService::class)->clearRoles(user()->getCurrentUser());
        }

        return redirect()->back()->with('success', t('profile.s_social.social_disconnected'));
    }

    /**
     * Hides the social network in the user's profile.
     */
    public function hideSocial(FluteRequest $fluteRequest, string $provider): Response
    {
        try {
            $this->throttle('profile_change_hide_social');
        } catch (Throwable $e) {
            return $this->error(__('auth.too_many_requests'));
        }

        $socialNetworkEntity = SocialNetwork::findOne(['key' => $provider]);

        if (!$socialNetworkEntity) {
            return redirect()->back()->withErrors(t('profile.errors.social_not_connected'));
        }

        $socialNetwork = UserSocialNetwork::findOne([
            'user_id' => user()->id,
            'socialNetwork_id' => $socialNetworkEntity->id,
        ]);

        if ($socialNetwork === null) {
            return redirect()->back()->withErrors(t('profile.errors.social_not_connected'));
        }

        $socialNetwork->hidden = !$socialNetwork->hidden;

        transaction($socialNetwork)->run();

        return $this->success();
    }

    /**
     * Returns an error response for social network authorization.
     */
    protected function socialError(string $error): Response
    {
        $redirectUrl = redirect('/profile/settings?tab=social')->getTargetUrl();
        $errorJs = json_encode($error, JSON_UNESCAPED_UNICODE);
        $redirectUrlJs = json_encode($redirectUrl, JSON_UNESCAPED_SLASHES);
        $origin = json_encode(request()->getSchemeAndHttpHost());

        return response()->make(
            "<script>if (window.opener) { window.opener.postMessage('authorization_error:' + "
            . $errorJs
            . ', '
            . $origin
            . '); window.close(); } else { alert('
            . $errorJs
            . '); window.location = '
            . $redirectUrlJs
            . '; }</script>',
        );
    }

    /**
     * Returns a successful response for social network authorization.
     */
    protected function socialSuccess(): Response
    {
        $redirectUrl = redirect('/profile/settings?tab=social')->getTargetUrl();
        $redirectUrlJs = json_encode($redirectUrl, JSON_UNESCAPED_SLASHES);
        $origin = json_encode(request()->getSchemeAndHttpHost());

        return response()->make(
            "<script>if (window.opener) { window.opener.postMessage('authorization_success', "
            . $origin
            . '); window.close(); } else { window.location = '
            . $redirectUrlJs
            . '; }</script>',
        );
    }
}
