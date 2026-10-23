/* ============================================================
   validacion.js — Formulario de Inscripción
   ------------------------------------------------------------
   - Valida cada campo en tiempo real (al salir y al escribir)
   - Envía los datos por fetch (AJAX) a procesar.php
   - Muestra los errores devueltos por el servidor
   ============================================================ */
'use strict';

/* ---------- Referencias del DOM ---------- */
const formulario = document.getElementById('formulario');
const aviso      = document.getElementById('aviso');
const boton      = document.getElementById('boton-enviar');
const textoBoton = boton.querySelector('.boton__texto');

const campos = {
  cedula:  document.getElementById('cedula'),
  nombre:  document.getElementById('nombre'),
  celular: document.getElementById('celular'),
  email:   document.getElementById('email')
};

/* ---------- Reglas de validación (iguales a las de PHP) ---------- */
const RE_CEDULA  = /^\d{6,15}$/;
const RE_NOMBRE  = /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]{2,}(?:[ '\-][A-Za-zÁÉÍÓÚÜÑáéíóúüñ]{2,})+$/;
const RE_CELULAR = /^\d{7,15}$/;
const RE_EMAIL   = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

/* ---------- Validadores por campo ---------- */
function validarCedula(mostrarError = true) {
  const valor = campos.cedula.value.trim();
  let mensaje = '';
  if (valor === '') {
    mensaje = 'La cédula es obligatoria.';
  } else if (!RE_CEDULA.test(valor)) {
    mensaje = 'Debe contener solo números (entre 6 y 15 dígitos).';
  }
  return aplicarResultado('cedula', mensaje, mostrarError);
}

function validarNombre(mostrarError = true) {
  const valor = campos.nombre.value.trim();
  let mensaje = '';
  if (valor === '') {
    mensaje = 'El nombre completo es obligatorio.';
  } else if (valor.length < 5) {
    mensaje = 'Debe tener al menos 5 caracteres.';
  } else if (!RE_NOMBRE.test(valor)) {
    mensaje = 'Ingrese nombres y apellidos (solo letras).';
  }
  return aplicarResultado('nombre', mensaje, mostrarError);
}

function validarCelular(mostrarError = true) {
  const valor = campos.celular.value.replace(/[ \-]/g, '').trim();
  let mensaje = '';
  if (valor === '') {
    mensaje = 'El número de celular es obligatorio.';
  } else if (!RE_CELULAR.test(valor)) {
    mensaje = 'Debe contener solo números (entre 7 y 15 dígitos).';
  }
  return aplicarResultado('celular', mensaje, mostrarError);
}

function validarEmail(mostrarError = true) {
  const valor = campos.email.value.trim();
  let mensaje = '';
  if (valor === '') {
    mensaje = 'El email es obligatorio.';
  } else if (!RE_EMAIL.test(valor)) {
    mensaje = 'Ingrese un email válido.';
  }
  return aplicarResultado('email', mensaje, mostrarError);
}

const validadores = {
  cedula:  validarCedula,
  nombre:  validarNombre,
  celular: validarCelular,
  email:   validarEmail
};

/* ---------- Utilidades visuales ---------- */
const grupo = (nombre) => campos[nombre].closest('.campo');
const smallError = (nombre) => document.getElementById('error-' + nombre);

/** Pinta el estado válido/inválido del campo. Devuelve true si es válido. */
function aplicarResultado(nombre, mensaje, mostrarError) {
  const g = grupo(nombre);
  if (mensaje === '') {
    g.classList.remove('campo--error');
    g.classList.add('campo--valido');
    smallError(nombre).textContent = '';
    return true;
  }
  if (mostrarError) {
    g.classList.remove('campo--valido');
    g.classList.add('campo--error');
    smallError(nombre).textContent = mensaje;
  }
  return false;
}

function mostrarAviso(tipo, mensaje) {
  aviso.className = 'aviso aviso--' + tipo;   /* 'exito' | 'error' */
  aviso.textContent = mensaje;
}

function ocultarAviso() {
  aviso.className = 'aviso aviso--oculto';
  aviso.textContent = '';
}

/* ---------- Validación en vivo ---------- */
Object.keys(campos).forEach((nombre) => {
  campos[nombre].addEventListener('blur', () => validadores[nombre]());
  campos[nombre].addEventListener('input', () => {
    /* Revalida en caliente solo si el campo ya estaba marcado con error */
    if (grupo(nombre).classList.contains('campo--error')) {
      validadores[nombre]();
    }
  });
});

/* Filtros de escritura: restringen caracteres desde el teclado */
campos.cedula.addEventListener('input', () => {
  campos.cedula.value = campos.cedula.value.replace(/[^\d]/g, '');
});

campos.nombre.addEventListener('input', () => {
  campos.nombre.value = campos.nombre.value.replace(/[^\p{L}'\-\s]/gu, '');
});

campos.celular.addEventListener('input', () => {
  campos.celular.value = campos.celular.value.replace(/[^\d]/g, '');
});

/* ---------- Envío del formulario (AJAX con fetch) ---------- */
formulario.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  ocultarAviso();

  /* 1) Validación local de todos los campos */
  const resultados = Object.keys(campos).map((nombre) => validadores[nombre]());
  if (resultados.includes(false)) {
    const primerError = Object.keys(campos).find((n) =>
      grupo(n).classList.contains('campo--error'));
    mostrarAviso('error', 'Por favor corrija los campos marcados.');
    if (primerError) campos[primerError].focus();
    return;
  }

  /* 2) Envío al servidor */
  boton.disabled = true;
  boton.classList.add('boton--cargando');
  textoBoton.textContent = 'Enviando…';

  try {
    const respuesta = await fetch('procesar.php', {
      method: 'POST',
      body: new FormData(formulario)
    });
    const datos = await respuesta.json();

    if (datos.ok) {
      /* Éxito: avisar, limpiar formulario y estados */
      mostrarAviso('exito', datos.mensaje || '¡Inscripción registrada con éxito!');
      formulario.reset();
      Object.keys(campos).forEach((n) =>
        grupo(n).classList.remove('campo--valido', 'campo--error'));
    } else {
      /* Errores devueltos por el servidor */
      mostrarAviso('error', datos.mensaje || 'No se pudo registrar la inscripción.');
      if (datos.errores) {
        Object.entries(datos.errores).forEach(([campo, msg]) => {
          if (campos[campo]) {
            grupo(campo).classList.remove('campo--valido');
            grupo(campo).classList.add('campo--error');
            smallError(campo).textContent = msg;
          }
        });
      }
    }
  } catch (error) {
    mostrarAviso('error',
      'No se pudo conectar con el servidor. Verifique que el proyecto ' +
      'se esté ejecutando a través de PHP (http://localhost/...).');
  } finally {
    boton.disabled = false;
    boton.classList.remove('boton--cargando');
    textoBoton.textContent = 'Inscribirme';
  }
});
