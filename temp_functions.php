function get_opportunity_docs(int $opportunityId): array
{
    $stmt = db()->prepare('SELECT id, path, original_name, mime, size, uploaded_by, uploaded_at FROM opportunity_docs WHERE opportunity_id = :oid ORDER BY uploaded_at DESC');
    $stmt->execute(['oid' => $opportunityId]);
    return $stmt->fetchAll();
}

function add_opportunity_doc(int $opportunityId, string $path, string $originalName, string $mime, int $size, int $uploadedBy): int
{
    $stmt = db()->prepare('INSERT INTO opportunity_docs (opportunity_id, path, original_name, mime, size, uploaded_by) VALUES (:oid, :path, :orig, :mime, :size, :uby)');
    $stmt->execute([
        'oid' => $opportunityId,
        'path' => $path,
        'orig' => $originalName,
        'mime' => $mime,
        'size' => $size,
        'uby' => $uploadedBy,
    ]);
    return (int)db()->lastInsertId();
}

function delete_opportunity_doc(int $docId, int $userId): bool
{
    // Check permission: admin or uploader
    $stmt = db()->prepare('SELECT uploaded_by FROM opportunity_docs WHERE id = :id');
    $stmt->execute(['id' => $docId]);
    $doc = $stmt->fetch();
    if (!$doc) return false;
    $user = current_user();
    if ($user['role'] !== 'admin' && $doc['uploaded_by'] != $userId) return false;
    
    $del = db()->prepare('DELETE FROM opportunity_docs WHERE id = :id');
    $del->execute(['id' => $docId]);
    return true;
}

function get_opportunity_audit(int $opportunityId): array
{
    $stmt = db()->prepare('SELECT a.old_status, a.new_status, a.changed_at, u.name AS changed_by_name
                           FROM opportunity_audit a
                           LEFT JOIN users u ON a.changed_by = u.id
                           WHERE a.opportunity_id = :oid
                           ORDER BY a.changed_at DESC');
    $stmt->execute(['oid' => $opportunityId]);
    return $stmt->fetchAll();
}

function update_opportunity_details(int $id, array $data, int $updatedBy): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $fields = [];
        $params = ['id' => $id];
        if (isset($data['notes'])) {
            $fields[] = 'notes = :notes';
            $params['notes'] = $data['notes'];
        }
        if (isset($data['phone'])) {
            $fields[] = 'phone = :phone';
            $params['phone'] = $data['phone'];
        }
        if (isset($data['address'])) {
            $fields[] = 'address = :address';
            $params['address'] = $data['address'];
        }
        if (isset($data['city'])) {
            $fields[] = 'city = :city';
            $params['city'] = $data['city'];
        }
        if (!empty($fields)) {
            $sql = 'UPDATE opportunities SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            // Log change
            log_opportunity_status_change($id, 'details_updated', 'details_updated', $updatedBy, $pdo);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}