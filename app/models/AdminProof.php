<?php
declare(strict_types=1);

final class AdminProof
{
    public static function upload(): ?string
    {
        $file = $_FILES['proof'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($file['error'] ?? -1) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name']) || ($file['size'] ?? 0) <= 0 || $file['size'] > 5 * 1024 * 1024) throw new DomainException('Envie um comprovante JPG, PNG, WEBP ou PDF de até 5 MB.');
        if (!class_exists('finfo')) throw new DomainException('O servidor precisa da extensão fileinfo para validar comprovantes.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'][$mime] ?? null;
        if ($ext === null) throw new DomainException('Tipo de comprovante não permitido.');
        if ($ext !== 'pdf' && !@getimagesize($file['tmp_name'])) throw new DomainException('Imagem inválida.');
        $dir = APP_ROOT . '/storage/proofs';
        if (!is_dir($dir) && !mkdir($dir,0750,true)) throw new DomainException('Não foi possível criar a pasta de comprovantes.');
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'],$dir . '/' . $name)) throw new DomainException('Não foi possível salvar o comprovante.');
        return 'proofs/' . $name;
    }

    public static function path(?string $name): ?string
    {
        if (!$name || !preg_match('#^proofs/[a-f0-9]{32}\.(jpg|png|webp|pdf)$#D',$name)) return null;
        return APP_ROOT . '/storage/' . $name;
    }
}
