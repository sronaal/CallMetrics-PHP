# Especificación de Requisitos — CallMetric Pro

**Producto:** CallMetric Pro — Plataforma de Análisis de Llamadas de Sistemas PBX  
**Versión:** MVP  
**Documento base:** Documento de Arquitectura de Software V3 y contexto completo del MVP  
**Enfoque:** Plataforma SaaS de observabilidad, análisis y monitoreo para infraestructuras PBX basadas en Asterisk/FreePBX

---

## 1. Objetivo del Documento

Definir los requisitos funcionales y no funcionales del MVP de **CallMetric Pro**, una plataforma SaaS orientada a la captura, procesamiento, visualización y monitoreo de eventos telefónicos provenientes de sistemas PBX Asterisk/FreePBX.

La plataforma no reemplaza la central telefónica ni interviene en el enrutamiento de llamadas. Su propósito es operar como una capa de inteligencia operacional sobre la infraestructura existente, transformando datos crudos como CDR, CEL y eventos AMI en dashboards, métricas, alertas y reportes.

---

## 2. Alcance del MVP

### 2.1 Incluido en el MVP

- Arquitectura multi-tenant con aislamiento lógico de datos por empresa.
- Gestión de roles jerárquicos:
  - Super Admin
  - Empresa Admin / Administrador de Tenant
  - Supervisor
  - Operador / Agente
- Agente de ingesta en Python desplegado en el entorno del cliente.
- Captura de eventos telefónicos desde Asterisk/FreePBX:
  - CDR
  - CEL
  - Eventos AMI
- Monitoreo básico de salud del PBX:
  - CPU
  - RAM
  - Disco
  - Canales SIP
  - Estado de conectividad
- Backend central desarrollado en Spring Boot.
- API REST para gestión de usuarios, PBX, reportes y configuración.
- Procesamiento de eventos y cálculo de métricas.
- Dashboards web interactivos.
- Actualización en tiempo real mediante WebSockets.
- Almacenamiento persistente en PostgreSQL.
- Caché y estado en tiempo real con Redis.
- Exportación de reportes en formato CSV.
- Alertas básicas configurables por umbrales.
- Seguridad basada en JWT, TLS y control de acceso por roles.

### 2.2 Fuera del alcance del MVP

- Aplicaciones móviles nativas para iOS o Android.
- Integraciones nativas con CRM/ERP externos.
- Canales no telefónicos como WhatsApp, redes sociales, correo o videollamadas.
- Enrutamiento de llamadas, marcación predictiva o reemplazo funcional de la PBX.
- Transcripción de voz en tiempo real.
- Análisis de sentimiento mediante modelos de lenguaje.
- Facturación automática y pasarela de pagos.

---

## 3. Requisitos Funcionales

| Código | Requisito Funcional | Descripción |
|---|---|---|
| RF01 | Gestión de usuarios y roles | El sistema debe permitir crear, editar, desactivar y asignar permisos diferenciados a usuarios según roles predefinidos: Super Admin, Empresa Admin, Supervisor y Operador. |
| RF02 | Autenticación y cierre de sesión seguro | El sistema debe validar credenciales mediante JWT, permitir inicio y cierre de sesión seguros, y gestionar refresh tokens para mantener sesiones activas de forma controlada. |
| RF03 | Registro y gestión de conexiones PBX | El sistema debe permitir registrar, configurar y revocar conexiones a múltiples servidores PBX Asterisk/FreePBX mediante tokens únicos por instancia, validando conectividad y estado. |
| RF04 | Integración con Asterisk (AMI/CDR/CEL) | El sistema debe establecer conexiones persistentes con AMI y/o archivos CDR/CEL de Asterisk para capturar eventos telefónicos y registros de llamadas en tiempo real. |
| RF05 | Visualización de dashboards interactivos | El sistema debe presentar paneles de control dinámicos con gráficos, KPIs y filtros avanzados, permitiendo la supervisión operacional sin recargar la página. |
| RF06 | Consulta de registros históricos | El sistema debe permitir buscar, filtrar y visualizar el historial de llamadas y métricas por rangos de fecha, empresa, extensión, cola o estado de llamada. |
| RF07 | Generación de reportes estadísticos | El sistema debe generar informes consolidados con métricas operativas como ASR, ACD, concurrencia y tasas de pérdida, aplicando filtros configurables por el usuario. |
| RF08 | Exportación de información | El sistema debe permitir descargar reportes y registros telefónicos en formato CSV, garantizando integridad de datos y respetando los permisos del usuario. |
| RF09 | Visualización de métricas en tiempo real | El sistema debe calcular y mostrar indicadores como llamadas activas, duración promedio, agentes conectados y estado de colas/troncales con actualizaciones automáticas. |
| RF10 | Actualización dinámica vía WebSockets | El sistema debe utilizar canales bidireccionales seguros para transmitir eventos PBX y actualizar dashboards/métricas con latencia mínima, objetivo menor a 500 ms. |
| RF11 | Configuración de reglas de alerta | El sistema debe permitir definir, activar y desactivar umbrales de alerta, por ejemplo: llamadas perdidas superiores a X, CPU de PBX superior a Y% o cola saturada, y notificar a los usuarios correspondientes. |
| RF12 | Monitoreo de salud de PBX | El sistema debe visualizar métricas de infraestructura del servidor PBX, tales como CPU, RAM, disco, canales SIP activos y su estado de conectividad con la plataforma central. |
| RF13 | Gestión multi-tenant y aislamiento de datos | El sistema debe garantizar que cada empresa o tenant solo acceda a su propia información, aplicando filtrado automático por contexto JWT y evitando fugas de datos cruzados. |

---

## 4. Requisitos No Funcionales

| Código | Requisito No Funcional | Descripción |
|---|---|---|
| RNF01 | Seguridad y cifrado | El sistema debe proteger contraseñas con bcrypt, utilizar JWT para autenticación y garantizar comunicaciones cifradas mediante TLS 1.3 en tránsito, tanto para HTTPS como para WSS. |
| RNF02 | Responsividad y adaptabilidad | La interfaz web debe renderizar correctamente en móviles, tablets y escritorios, manteniendo usabilidad y consistencia visual en resoluciones desde 320 px. |
| RNF03 | Documentación técnica | El proyecto debe incluir documentación actualizada de arquitectura, API mediante OpenAPI/Swagger, guías de despliegue, configuración del agente y mantenimiento de componentes. |
| RNF04 | Disponibilidad y resiliencia | La plataforma debe mantener un uptime objetivo del 99.5% en componentes críticos, implementando reconexión automática del agente, health checks y manejo elegante de fallos de red. |
| RNF05 | Rendimiento y latencia | El sistema debe procesar eventos AMI/CDR y actualizar dashboards con latencia menor a 500 ms, soportando hasta aproximadamente 10,000 eventos por minuto por instancia sin degradación perceptible. |
| RNF06 | Persistencia y almacenamiento | Los datos históricos deben almacenarse en PostgreSQL con estructuración relacional, soportando particionamiento temporal, backups automáticos y retención configurable por tenant. |
| RNF07 | Compatibilidad de navegadores | La aplicación frontend debe funcionar correctamente en versiones estables recientes de Chrome, Firefox, Edge y Safari, con soporte nativo para WebSockets y ES6+. |
| RNF08 | Comunicación en tiempo real confiable | Los canales WebSocket deben implementar reconexión automática, manejo de pérdida de paquetes y fallback a polling corto si la conexión persistente falla temporalmente. |
| RNF09 | Mantenibilidad y calidad de código | El código base debe seguir Clean Architecture, incluir cobertura de pruebas unitarias y de integración igual o superior al 80%, y utilizar pipelines CI/CD para despliegues automatizados y trazables. |
| RNF10 | Integridad y consistencia de datos | El sistema debe garantizar validación de esquemas, transacciones ACID para operaciones críticas y sincronización idempotente entre el agente Python y el backend Spring Boot. |
| RNF11 | Escalabilidad horizontal | La arquitectura debe permitir el escalado independiente de componentes como backend, Redis y réplicas de lectura de PostgreSQL, mediante contenedores Docker y preparación para orquestación con Kubernetes. |

---

## 5. Límites Técnicos y Operativos

- El agente requiere acceso de lectura a la interfaz AMI y/o a los archivos CDR/CEL de Asterisk.
- El agente no debe modificar configuraciones de enrutamiento ni interferir con el tráfico de voz.
- La plataforma opera bajo un modelo SaaS cloud-first.
- El cliente solo despliega el agente y configura la conexión segura.
- El rendimiento está optimizado para procesar hasta aproximadamente 10,000 eventos por minuto por instancia backend.
- La latencia objetivo del dashboard debe ser menor a 500 ms.
- La retención de datos históricos se configura por tenant.
- Se deben contemplar políticas de archivado automático para mantener la eficiencia de PostgreSQL.

---

## 6. Criterios de Éxito del MVP

- Conexión simultánea y estable a al menos 3 PBX de diferentes entornos.
- Ingesta continua de CDR y eventos con pérdida de datos menor al 0.1%.
- Dashboard principal cargando en menos de 2 segundos.
- Dashboard actualizándose en menos de 500 ms.
- Aislamiento lógico verificado, sin fugas de datos entre tenants en pruebas básicas.
- Exportación CSV funcional con filtros por fecha, empresa, cola y extensión.
- Alertas básicas activadas y notificadas al superar los umbrales configurados por el usuario.

---

## 7. Entidades Principales del Dominio

| Entidad | Descripción |
|---|---|
| Empresa | Representa un cliente o tenant dentro de la plataforma SaaS. |
| PBX | Representa un servidor telefónico conectado a la plataforma. |
| Extensión | Usuario SIP interno asociado a un PBX. |
| Cola | Representa una queue de atención telefónica. |
| Agente | Operador telefónico que atiende llamadas. |
| Llamada | Registro CDR consolidado con información de origen, destino, duración, estado, grabación y timestamps. |
| Evento | Evento proveniente de AMI, CEL o del sistema interno de monitoreo. |

---

## 8. Roles del Sistema

| Rol | Descripción General |
|---|---|
| Super Admin | Gestiona empresas/tenants, monitorea la salud de la plataforma, administra licencias y brinda soporte de alto nivel. |
| Empresa Admin / Tenant Admin | Configura PBXs de su organización, gestiona usuarios internos, define reglas de alerta y consulta reportes corporativos. |
| Supervisor Operativo | Monitorea colas y agentes en tiempo real, analiza métricas de desempeño, genera reportes operativos y actúa sobre alertas. |
| Operador / Agente | Consulta métricas personales, visualiza el estado de extensiones autorizadas y accede a reportes básicos. |

---

## 9. Requisitos Asociados a Datos y Reportes

| Código | Requisito | Descripción |
|---|---|---|
| RD01 | Persistencia de CDR | El sistema debe almacenar registros de llamadas procesados desde CDR, conservando atributos relevantes como origen, destino, duración, estado y fechas. |
| RD02 | Persistencia de eventos | El sistema debe almacenar eventos provenientes de AMI, CEL y monitoreo del agente cuando aplique. |
| RD03 | Aislamiento por tenant | Todos los registros operativos deben estar asociados al tenant correspondiente y ser accesibles únicamente según el contexto de seguridad. |
| RD04 | Filtros de consulta | El sistema debe permitir consultas por fecha, empresa, PBX, cola, extensión, agente y estado de llamada, según el rol del usuario. |
| RD05 | Exportación CSV | Los reportes y registros exportables deben generarse en formato CSV respetando filtros y permisos del usuario. |

---

## 10. Requisitos de Seguridad

| Código | Requisito | Descripción |
|---|---|---|
| SEG01 | Autenticación JWT | El acceso a la plataforma debe realizarse mediante tokens JWT con access token de corta duración y refresh token. |
| SEG02 | Protección de contraseñas | Las contraseñas deben almacenarse usando bcrypt. |
| SEG03 | Cifrado en tránsito | Toda comunicación entre cliente, agente y backend debe usar TLS 1.3 mediante HTTPS/WSS. |
| SEG04 | Autorización por roles | El sistema debe aplicar control de acceso basado en roles para limitar las acciones y vistas disponibles por usuario. |
| SEG05 | Aislamiento multi-tenant | El backend debe aplicar filtros automáticos por tenant para evitar acceso cruzado a datos entre empresas. |
| SEG06 | Tokens por PBX | Cada conexión PBX debe usar un token único o credencial específica para autenticar al agente frente al backend. |
| SEG07 | Auditoría | El sistema debe contemplar logs de auditoría estructurados para acciones relevantes de seguridad y administración. |

---

## 11. Requisitos de Tiempo Real

| Código | Requisito | Descripción |
|---|---|---|
| TR01 | Eventos de llamada | El sistema debe permitir visualizar eventos de llamada iniciada y llamada finalizada. |
| TR02 | Estado de colas | El sistema debe actualizar el estado de colas y agentes en tiempo real. |
| TR03 | Estado de PBX | El sistema debe reflejar cambios relevantes de conectividad y salud del PBX. |
| TR04 | Actualización de dashboards | Los dashboards deben actualizarse sin recarga completa de página mediante WebSockets. |
| TR05 | Reconexión automática | El cliente y el agente deben soportar reconexión automática ante caídas temporales de conexión. |
| TR06 | Baja latencia | La actualización de métricas críticas debe tener una latencia objetivo menor a 500 ms. |

---

## 12. Requisitos de Monitoreo y Alertas

| Código | Requisito | Descripción |
|---|---|---|
| AL01 | Alertas por llamadas perdidas | El sistema debe permitir configurar alertas cuando la cantidad o tasa de llamadas perdidas supere un umbral definido. |
| AL02 | Alertas por CPU | El sistema debe permitir alertar cuando el uso de CPU del PBX supere un umbral configurado. |
| AL03 | Alertas por RAM | El sistema debe permitir alertar cuando el uso de memoria RAM supere un umbral configurado. |
| AL04 | Alertas por saturación de colas | El sistema debe permitir alertar cuando una cola presente condiciones de saturación. |
| AL05 | Alertas por troncales caídos | El sistema debe permitir notificar la caída o indisponibilidad de troncales cuando sea detectable. |
| AL06 | Notificación en interfaz | Las alertas deben ser visibles para los usuarios autorizados dentro de la interfaz web. |

---

## 13. Supuestos del MVP

- El cliente cuenta con un PBX Asterisk/FreePBX accesible para la instalación del agente.
- El agente puede leer CDR, CEL y/o conectarse a AMI según la configuración del PBX.
- La plataforma no modifica la configuración de ruteo de llamadas del PBX.
- El despliegue del agente se realiza en el entorno del cliente.
- El backend, frontend, base de datos y caché se despliegan en un entorno cloud o centralizado.
- El MVP no incluye facturación automática ni integraciones con CRM/ERP.
