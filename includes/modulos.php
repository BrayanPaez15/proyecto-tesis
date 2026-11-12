<?php
/**
 * Registro central de los once modulos del directorio.
 *
 * Cada modulo es un tipo de actor (tabla tipos_actor) cuyos datos comunes viven en
 * `contactos` y los propios en su tabla `detalle_*`. Por modulo se indican:
 *  - tipo: nombre en tipos_actor.
 *  - archivo, titulo, campo_busqueda y columnas (campo => etiqueta): lo que usan los
 *    formularios, las listas, exportar.php, buscar.php e importar.php.
 *  - detalle: tabla detalle_* del tipo (null si no necesita) y vinculacion (solo
 *    detalle_personal, que comparten practicantes y equipo).
 *  - mapa: donde se guarda cada campo del formulario: 'c.columna' en contactos o
 *    'd.columna' en la tabla de detalle. Algunos campos cambian de nombre al guardarse
 *    (por ejemplo la «caja» de Asocajas es contactos.nombre).
 *  - booleanos y fechas: campos que se convierten (Si/No a 1/0; vacio a NULL).
 *  - agenda: el modulo guarda su disponibilidad (contactos.disponibilidad).
 * includes/contactos.php arma las consultas a partir de esta lista.
 */
return [
    'artistas' => ['tipo' => 'Artista', 'archivo' => 'artistas', 'titulo' => 'Artistas', 'campo_busqueda' => 'nombre',
        'columnas' => ['nombre' => 'Nombre', 'perfil' => 'Perfil', 'organizacion' => 'Organización', 'agenda' => 'Agenda', 'telefono' => 'Teléfono', 'correo' => 'Correo', 'filbo' => 'Filbo'],
        'detalle' => 'detalle_artista', 'agenda' => true, 'booleanos' => ['filbo'],
        'mapa' => ['nombre' => 'c.nombre', 'perfil' => 'c.perfil', 'organizacion' => 'c.organizacion', 'agenda' => 'd.agenda', 'telefono' => 'c.telefono', 'correo' => 'c.correo', 'filbo' => 'd.filbo']],
    'asocajas' => ['tipo' => 'Asocaja', 'archivo' => 'asocajas', 'titulo' => 'Asocajas', 'campo_busqueda' => 'caja',
        'columnas' => ['caja' => 'Caja', 'departamento' => 'Departamento', 'cargo' => 'Cargo', 'contacto' => 'Contacto', 'telefono' => 'Teléfono', 'direccion' => 'Dirección', 'correo' => 'Correo'],
        'detalle' => null,
        'mapa' => ['caja' => 'c.nombre', 'departamento' => 'c.departamento', 'cargo' => 'c.cargo', 'contacto' => 'c.contacto', 'telefono' => 'c.telefono', 'direccion' => 'c.direccion', 'correo' => 'c.correo']],
    'directivos' => ['tipo' => 'Directivo', 'archivo' => 'directivos', 'titulo' => 'Directivos', 'campo_busqueda' => 'nombre',
        'columnas' => ['titulo' => 'Título', 'nombre' => 'Nombre', 'apellido' => 'Apellido', 'cedula' => 'Cédula', 'calidad' => 'Calidad', 'estado' => 'Estado', 'entidad' => 'Entidad', 'cargo' => 'Cargo', 'celular' => 'Celular', 'telefono' => 'Teléfono', 'correo' => 'Correo', 'integrante' => 'Integrante', 'vigencia' => 'Vigencia'],
        'detalle' => 'detalle_directivo',
        'mapa' => ['titulo' => 'd.titulo', 'nombre' => 'c.nombre', 'apellido' => 'c.apellido', 'cedula' => 'd.cedula', 'calidad' => 'd.calidad', 'estado' => 'd.estado', 'entidad' => 'c.organizacion', 'cargo' => 'c.cargo', 'celular' => 'd.celular', 'telefono' => 'c.telefono', 'correo' => 'c.correo', 'integrante' => 'd.integrante', 'vigencia' => 'd.vigencia']],
    'editoriales' => ['tipo' => 'Editorial', 'archivo' => 'editoriales', 'titulo' => 'Editoriales', 'campo_busqueda' => 'nombre',
        'columnas' => ['nombre' => 'Nombre', 'nit' => 'NIT', 'contacto' => 'Contacto', 'telefono' => 'Teléfono', 'direccion' => 'Dirección', 'correo' => 'Correo', 'descuento' => 'Descuento'],
        'detalle' => 'detalle_editorial',
        'mapa' => ['nombre' => 'c.nombre', 'nit' => 'c.nit', 'contacto' => 'c.contacto', 'telefono' => 'c.telefono', 'direccion' => 'c.direccion', 'correo' => 'c.correo', 'descuento' => 'd.descuento']],
    'instituciones-e' => ['tipo' => 'Institución educativa', 'archivo' => 'instituciones-e', 'titulo' => 'Instituciones Educativas', 'campo_busqueda' => 'nombre',
        'columnas' => ['clase' => 'Clase', 'nombre' => 'Nombre', 'nit' => 'NIT', 'calidad' => 'Calidad', 'jornada' => 'Jornada', 'contacto' => 'Contacto', 'cargo' => 'Cargo', 'telefono' => 'Teléfono', 'direccion' => 'Dirección', 'correo' => 'Correo', 'ciudad' => 'Ciudad'],
        'detalle' => 'detalle_institucion',
        'mapa' => ['clase' => 'd.clase', 'nombre' => 'c.nombre', 'nit' => 'c.nit', 'calidad' => 'd.calidad', 'jornada' => 'd.jornada', 'contacto' => 'c.contacto', 'cargo' => 'c.cargo', 'telefono' => 'c.telefono', 'direccion' => 'c.direccion', 'correo' => 'c.correo', 'ciudad' => 'c.ciudad']],
    'medios' => ['tipo' => 'Medio', 'archivo' => 'medios', 'titulo' => 'Medios', 'campo_busqueda' => 'nombre',
        'columnas' => ['categoria' => 'Categoría', 'medio' => 'Medio', 'fuente' => 'Fuente', 'nombre' => 'Nombre', 'correo' => 'Correo', 'telefono' => 'Teléfono', 'telefono2' => 'Teléfono 2', 'direccion' => 'Dirección'],
        'detalle' => 'detalle_medio',
        'mapa' => ['categoria' => 'd.categoria', 'medio' => 'd.medio', 'fuente' => 'd.fuente', 'nombre' => 'c.nombre', 'correo' => 'c.correo', 'telefono' => 'c.telefono', 'telefono2' => 'c.telefono_alterno', 'direccion' => 'c.direccion']],
    'mercadeo' => ['tipo' => 'Mercadeo', 'archivo' => 'mercadeo', 'titulo' => 'Mercadeo', 'campo_busqueda' => 'nombre',
        'columnas' => ['empresa' => 'Empresa', 'nombre' => 'Nombre', 'cargo' => 'Cargo', 'tema' => 'Tema', 'contacto' => 'Contacto', 'telefono' => 'Teléfono', 'correo' => 'Correo', 'direccion' => 'Dirección', 'proyecto' => 'Proyecto', 'patrocinio' => 'Patrocinio'],
        'detalle' => 'detalle_mercadeo',
        'mapa' => ['empresa' => 'c.organizacion', 'nombre' => 'c.nombre', 'cargo' => 'c.cargo', 'tema' => 'd.tema', 'contacto' => 'c.contacto', 'telefono' => 'c.telefono', 'correo' => 'c.correo', 'direccion' => 'c.direccion', 'proyecto' => 'd.proyecto', 'patrocinio' => 'd.patrocinio']],
    'practicantes' => ['tipo' => 'Practicante', 'archivo' => 'practicantes', 'titulo' => 'Practicantes', 'campo_busqueda' => 'nombre',
        'columnas' => ['nombre' => 'Nombre', 'telefono' => 'Teléfono', 'correo' => 'Correo', 'direccion' => 'Dirección', 'disciplina' => 'Disciplina', 'generacion' => 'Generación', 'inicio' => 'Fecha inicio', 'fin' => 'Fecha fin', 'cumple' => 'Cumpleaños', 'contacto' => 'Contacto de emergencia', 'telefono_contacto' => 'Teléfono contacto'],
        'detalle' => 'detalle_personal', 'vinculacion' => 'practicante', 'fechas' => ['inicio', 'fin', 'cumple'],
        'mapa' => ['nombre' => 'c.nombre', 'telefono' => 'c.telefono', 'correo' => 'c.correo', 'direccion' => 'c.direccion', 'disciplina' => 'd.disciplina', 'generacion' => 'd.generacion', 'inicio' => 'd.inicio', 'fin' => 'd.fin', 'cumple' => 'd.cumple', 'contacto' => 'd.contacto_emergencia', 'telefono_contacto' => 'd.telefono_emergencia']],
    'proveedores' => ['tipo' => 'Proveedor', 'archivo' => 'proveedores', 'titulo' => 'Proveedores', 'campo_busqueda' => 'nombre',
        'columnas' => ['pais' => 'País', 'nombre' => 'Nombre', 'direccion' => 'Dirección', 'telefono' => 'Teléfono', 'correo' => 'Correo'],
        'detalle' => null,
        'mapa' => ['pais' => 'c.pais', 'nombre' => 'c.nombre', 'direccion' => 'c.direccion', 'telefono' => 'c.telefono', 'correo' => 'c.correo']],
    'talleristas' => ['tipo' => 'Tallerista', 'archivo' => 'talleristas', 'titulo' => 'Talleristas', 'campo_busqueda' => 'nombre',
        'columnas' => ['nombre' => 'Nombre', 'telefono' => 'Teléfono', 'correo' => 'Correo', 'cargo' => 'Cargo', 'perfil' => 'Perfil'],
        'detalle' => null, 'agenda' => true,
        'mapa' => ['nombre' => 'c.nombre', 'telefono' => 'c.telefono', 'correo' => 'c.correo', 'cargo' => 'c.cargo', 'perfil' => 'c.perfil']],
    'team' => ['tipo' => 'Equipo Pombo', 'archivo' => 'team', 'titulo' => 'Team', 'campo_busqueda' => 'nombre',
        'columnas' => ['nombre' => 'Nombre', 'apellido' => 'Apellido', 'celular' => 'Celular', 'correo' => 'Correo', 'cargo' => 'Cargo', 'cumple' => 'Cumpleaños', 'contacto' => 'Contacto de emergencia', 'telefono' => 'Teléfono', 'inicio' => 'Fecha inicio', 'fin' => 'Fecha fin'],
        'detalle' => 'detalle_personal', 'vinculacion' => 'equipo', 'fechas' => ['inicio', 'fin', 'cumple'],
        'mapa' => ['nombre' => 'c.nombre', 'apellido' => 'c.apellido', 'celular' => 'c.telefono_alterno', 'correo' => 'c.correo', 'cargo' => 'c.cargo', 'cumple' => 'd.cumple', 'contacto' => 'd.contacto_emergencia', 'telefono' => 'c.telefono', 'inicio' => 'd.inicio', 'fin' => 'd.fin']],
];
