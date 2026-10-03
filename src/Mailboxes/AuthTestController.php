<?php

declare(strict_types=1);

namespace Horde\Imp\Mailboxes;

use Horde\Core\Auth\AuthCredentialStore;
use Horde\Core\Service\CredentialStore;
use Horde\Core\Service\ServicePurpose;
use Horde\Core\Service\GrantStrategy;
use Horde\Horde\Service\SessionToCredentialStoreProvisioner;
use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Horde\Traits\RedirectResponseTrait;
use Horde\Imp\ImpBackendConfig;
use Horde_Registry;
use Psr\Http\Message\ResponseInterface;
use Horde\Http\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test controller for password credential system.
 *
 * Shows credential status for each mail backend and allows provisioning
 * from session.
 */
class AuthTestController implements RequestHandlerInterface
{
    use HtmlResponseTrait;
    use RedirectResponseTrait;

    public function __construct(
        private readonly CredentialStore $store,
        private readonly AuthCredentialStore $authCredentials,
        private readonly ImpBackendConfig $backendConfig,
        private readonly Horde_Registry $registry,
        private readonly ResponseFactory $responseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $body = $request->getParsedBody() ?? [];
        // Handle POST - provision from session
        if ($method === 'POST' && isset($body['backend_id'])) {
            return $this->provisionFromSession($body['backend_id']);
        }

        // GET - show status
        return $this->showStatus($request);
    }

    private function showStatus(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->registry->getAuth();
        $backends = $this->backendConfig->toArray();
        $results = [];
        $message = $request->getQueryParams()['message'] ?? null;
        foreach ($backends as $backendId => $backend) {
            if (isset($backend['disabled']) && $backend['disabled']) {
                continue;
            }

            $credentialSystem = $backend['credential_system'] ?? 'password';

            // Skip OAuth backends for now
            if ($credentialSystem === 'oauth') {
                continue;
            }

            $purpose = ServicePurpose::of('imap', GrantStrategy::Isolated);

            try {
                $credential = $this->store->find($userId, $backendId, $purpose);

                if ($credential) {
                    $structured = $credential->asStructured();
                    $results[] = [
                        'backend_id' => $backendId,
                        'backend_name' => $backend['name'] ?? $backendId,
                        'status' => 'found',
                        'username' => $structured['username'] ?? 'N/A',
                        'has_password' => isset($structured['password']),
                        'created_at' => date('Y-m-d H:i:s', $credential->createdAt()),
                    ];
                } else {
                    $results[] = [
                        'backend_id' => $backendId,
                        'backend_name' => $backend['name'] ?? $backendId,
                        'status' => 'not_found',
                    ];
                }
            } catch (\Throwable $e) {
                $results[] = [
                    'backend_id' => $backendId,
                    'backend_name' => $backend['name'] ?? $backendId,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $html = $this->renderView($results, $message);
        return $this->htmlResponse($html);
    }

    private function provisionFromSession(string $backendId): ResponseInterface
    {
        $userId = $this->registry->getAuth();
        $creds = $this->registry->getAuthCredential();
        $password = $creds['password'] ?? null;
        $purpose = ServicePurpose::of('imap', GrantStrategy::Isolated);

        $provisioner = new SessionToCredentialStoreProvisioner(
            $this->authCredentials,
            $this->store
        );

        try {
            $result = $provisioner->provision($userId, $backendId, $purpose);
            $message = 'Provisioning result: ' . $result->action->name;
        } catch (\Throwable $e) {
            $message = 'Provisioning failed: ' . $e->getMessage();
        }

        // Redirect back to GET with message
        // TODO: Use the routes framework instead of this hardcoding.
        return $this->redirect('/imp/mailboxes/authtest/?message=' . urlencode($message));
    }

    private function renderView(array $results, ?string $message): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>IMP Auth Test - Password Credentials</title>
</head>
<body>
    <h1>IMP Auth Test - Password Credentials</h1>

    <?php if ($message): ?>
    <p><strong><?php echo htmlspecialchars($message) ?></strong></p>
    <?php endif; ?>

    <p>Testing password credential storage for mail backends.</p>

    <h2>Backend Status</h2>

    <?php if (empty($results)): ?>
    <p>No backends configured.</p>
    <?php else: ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>Backend ID</th>
                <th>Backend Name</th>
                <th>Status</th>
                <th>Details</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $result): ?>
            <tr>
                <td><?php echo htmlspecialchars($result['backend_id']) ?></td>
                <td><?php echo htmlspecialchars($result['backend_name']) ?></td>
                <td><?php echo htmlspecialchars($result['status']) ?></td>
                <td>
                    <?php if ($result['status'] === 'found'): ?>
                        Username: <?php echo htmlspecialchars($result['username']) ?><br>
                        Has password: <?php echo $result['has_password'] ? 'Yes' : 'No' ?><br>
                        Created: <?php echo htmlspecialchars($result['created_at']) ?>
                    <?php elseif ($result['status'] === 'not_found'): ?>
                        No credential set
                    <?php elseif ($result['status'] === 'error'): ?>
                        Error: <?php echo htmlspecialchars($result['error']) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($result['status'] === 'not_found'): /* TODO: Use the routes framework instead of hardcoding this path. Hardcoding this path is bad*/?>
                    <?php echo "User: " . $this->registry->getAuth(); $creds = $this->registry->getAuthCredential(); echo empty($creds['password']) ? ' (No password in session)' : ' (Password in session)'; ?>
                    <form method="POST" action="/imp/mailboxes/authtest/">
                        <input type="hidden" name="backend_id" value="<?php echo htmlspecialchars($result['backend_id']) ?>">
                        <button type="submit">Retrieve from Session</button>
                    </form>
                    <?php else: ?>
                    —
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <hr>
    <p><small>This is a test page for the password credential system.</small></p>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
