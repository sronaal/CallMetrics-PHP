# ⚠️ ADVERTENCIA DE SEGURIDAD CRÍTICA

## Passwords por Defecto en seed.sql

Los passwords en este archivo de seed han sido **marcados como vulnerables** y deben ser cambiados INMEDIATAMENTE después del deploy en producción.

### Acciones Requeridas:

1. **INMEDIATO**: Cambiar todos los passwords por defecto después de instalar
2. **Forzar primer cambio de password**: Implementar flujo de "primer ingreso"
3. **Nunca commitear passwords reales**: Este archivo no debe estar en el repositorio en producción

### Procedimiento de Hardening:

```sql
-- Generar nuevo hash para admin principal
-- Ejecutar en PHP:
<?php
echo password_hash('TU_PASSWORD_SEGURO_AQUI', PASSWORD_BCRYPT, ['cost' => 12]);
?>

-- Actualizar en BD:
UPDATE usuarios SET password_hash = '<nuevo_hash>' WHERE email = 'admin@empresa.com';
```

### Mejores Prácticas:

- ✅ Usar variables de entorno para passwords iniciales
- ✅ Forzar cambio de password en primer login
- ✅ Implementar política de passwords fuertes
- ✅ Rotar passwords periódicamente
- ✅ Nunca usar passwords de ejemplo en producción

