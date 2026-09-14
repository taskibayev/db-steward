<?php

namespace App\Controller;

use App\ClientDatabase\ClientDatabaseBrowser;
use App\ClientDatabase\ClientDatabaseReadFailed;
use App\ClientDatabase\DatabaseAccessDenied;
use App\ClientDatabase\SchemaTable;
use App\ClientDatabase\UnknownSchemaIdentifier;
use App\Dto\BrowseRowsQuery;
use App\Entity\ClientConnection;
use App\Entity\User;
use App\Permission\PermissionChecker;
use App\Permission\PermissionOperation;
use App\Repository\ClientConnectionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/connections/{id}')]
final class DatabaseBrowserController extends AbstractController
{
    #[Route('/schema', name: 'api_database_schema', methods: ['GET'])]
    public function schema(
        string $id,
        ClientConnectionRepository $connections,
        ClientDatabaseBrowser $browser,
        PermissionChecker $permissions,
    ): JsonResponse {
        $connection = $this->findConnection($id, $connections);
        $user = $this->getUser();
        if (!$connection instanceof ClientConnection || !$user instanceof User) {
            return $this->json(['error' => 'connection_not_found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $tables = $browser->schema($user, $connection);
        } catch (DatabaseAccessDenied) {
            return $this->json(['error' => 'database_access_denied'], Response::HTTP_FORBIDDEN);
        } catch (ClientDatabaseReadFailed) {
            return $this->json(['error' => 'client_database_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json(['items' => array_map(
            fn (SchemaTable $table): array => [
                ...$table->toArray(),
                'permissions' => [
                    'select' => true,
                    'insert' => !$table->isReadOnly() && $permissions->isAllowed($user, $connection, $table->name, PermissionOperation::Insert),
                    'update' => !$table->isReadOnly() && $permissions->isAllowed($user, $connection, $table->name, PermissionOperation::Update),
                    'delete' => !$table->isReadOnly() && $permissions->isAllowed($user, $connection, $table->name, PermissionOperation::Delete),
                ],
            ],
            $tables,
        )]);
    }

    #[Route('/tables/{table}/rows', name: 'api_database_rows', methods: ['GET'], requirements: ['table' => '[^/]+'])]
    public function rows(
        string $id,
        string $table,
        ClientConnectionRepository $connections,
        ClientDatabaseBrowser $browser,
        #[MapQueryString]
        BrowseRowsQuery $query = new BrowseRowsQuery(),
    ): JsonResponse {
        $connection = $this->findConnection($id, $connections);
        $user = $this->getUser();
        if (!$connection instanceof ClientConnection || !$user instanceof User) {
            return $this->json(['error' => 'connection_not_found'], Response::HTTP_NOT_FOUND);
        }

        try {
            return $this->json($browser->rows($user, $connection, $table, $query)->toArray());
        } catch (DatabaseAccessDenied) {
            return $this->json(['error' => 'table_access_denied'], Response::HTTP_FORBIDDEN);
        } catch (UnknownSchemaIdentifier) {
            return $this->json(['error' => 'table_or_column_not_found'], Response::HTTP_NOT_FOUND);
        } catch (ClientDatabaseReadFailed) {
            return $this->json(['error' => 'client_database_unavailable'], Response::HTTP_BAD_GATEWAY);
        }
    }

    private function findConnection(string $id, ClientConnectionRepository $connections): ?ClientConnection
    {
        return Uuid::isValid($id) ? $connections->find($id) : null;
    }
}
