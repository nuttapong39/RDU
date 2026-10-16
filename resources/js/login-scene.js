/**
 * Login page 3D scene: a glowing "HOSxP XE" core with the 13 KPIs orbiting it as glass capsules.
 * Capsule colour = KPI category (same colours as the dashboard); the ring around it = the KPI's status
 * from the last overview (CONTEXT.md: ภาพสถานะล่าสุด). Light particles stream from the core to every
 * capsule: data flowing from HOSxP into the indicators.
 *
 * Decorative only — the container is aria-hidden; the status summary is in the page text.
 * Falls back to the page's static background when WebGL is missing, renders one still frame under
 * prefers-reduced-motion, and pauses while the tab is hidden.
 */
import * as THREE from 'three';

const CATEGORY_COLORS = {
    Antibiotic:     0xdc2626,
    DrugQuality:    0x059669,
    PatientSafety:  0x4f46e5,
    ChronicDisease: 0x0891b2,
};
const STATUS = {
    pass:    { color: 0x22c55e, label: 'ผ่านเกณฑ์' },
    warning: { color: 0xf59e0b, label: 'ใกล้เป้าหมาย' },
    fail:    { color: 0xef4444, label: 'ไม่ผ่านเกณฑ์' },
    na:      { color: 0x94a3b8, label: 'ไม่มีข้อมูล' },
};
const CORE_COLOR = 0x0d9488;
const INNER_RING_COUNT = 6;   // KPIs on the inner orbit; the rest go on the outer one

function webglAvailable() {
    try {
        const canvas = document.createElement('canvas');
        const gl = window.WebGLRenderingContext && (canvas.getContext('webgl2') || canvas.getContext('webgl'));
        gl?.getExtension('WEBGL_lose_context')?.loseContext();   // free the probe's context right away
        return !!gl;
    } catch {
        return false;
    }
}

/** A text label drawn on a canvas, used as a sprite under the core. */
function textSprite(text) {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 128;
    const ctx = canvas.getContext('2d');
    ctx.font = '700 64px Inter, "IBM Plex Sans Thai", system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#0f766e';
    ctx.fillText(text, 256, 64);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: texture, transparent: true, depthWrite: false }));
    sprite.scale.set(2.2, 0.55, 1);
    return sprite;
}

/** A soft round glow texture for particles and the core halo. */
function glowTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 64;
    const ctx = canvas.getContext('2d');
    const g = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
    g.addColorStop(0, 'rgba(255,255,255,1)');
    g.addColorStop(0.35, 'rgba(255,255,255,.55)');
    g.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 64, 64);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    return texture;
}

function start(container, kpis) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const small = () => container.clientWidth < 900;

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    container.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
    camera.position.set(0, 1.2, 11);
    camera.lookAt(0, 0, 0);

    scene.add(new THREE.AmbientLight(0xffffff, 1.4));
    const key = new THREE.DirectionalLight(0xffffff, 1.6);
    key.position.set(4, 6, 8);
    scene.add(key);
    const coreLight = new THREE.PointLight(CORE_COLOR, 18, 9, 1.6);
    scene.add(coreLight);

    // Everything orbits inside `system`, which is tilted and follows the mouse a little.
    const system = new THREE.Group();
    system.rotation.x = 0.5;   // flat, wide orbits: they pass behind the text and the card
    scene.add(system);

    // ---- Core: HOSxP XE ----
    const core = new THREE.Mesh(
        new THREE.IcosahedronGeometry(0.85, 4),
        new THREE.MeshPhysicalMaterial({ color: 0x5eead4, emissive: CORE_COLOR, emissiveIntensity: 0.55, roughness: 0.25, clearcoat: 1, transparent: true, opacity: 0.92 }),
    );
    system.add(core);
    const shell = new THREE.Mesh(
        new THREE.IcosahedronGeometry(1.2, 1),
        new THREE.MeshBasicMaterial({ color: CORE_COLOR, wireframe: true, transparent: true, opacity: 0.22 }),
    );
    system.add(shell);
    const glow = glowTexture();
    const halo = new THREE.Sprite(new THREE.SpriteMaterial({ map: glow, color: 0x5eead4, transparent: true, opacity: 0.55, depthWrite: false, blending: THREE.AdditiveBlending }));
    halo.scale.set(4.2, 4.2, 1);
    system.add(halo);
    const label = textSprite('HOSxP XE');
    label.position.set(0, -1.65, 0);
    scene.add(label);   // outside `system` so it stays upright

    // ---- Orbits: KPIs split over two rings ----
    const rings = [{ radius: 2.7, speed: 0.11 }, { radius: 3.7, speed: -0.075 }];
    const ringGroups = rings.map(({ radius }, i) => {
        const group = new THREE.Group();
        group.rotation.z = i === 0 ? 0.12 : -0.18;
        const path = new THREE.Mesh(
            new THREE.TorusGeometry(radius, 0.008, 6, 160),
            new THREE.MeshBasicMaterial({ color: 0x64748b, transparent: true, opacity: 0.25 }),
        );
        path.rotation.x = Math.PI / 2;
        group.add(path);
        system.add(group);
        return group;
    });

    const capsuleGeometry = new THREE.CapsuleGeometry(0.16, 0.42, 6, 16);
    const ringGeometry = new THREE.TorusGeometry(0.42, 0.028, 8, 48);
    const capsules = kpis.map((kpi, index) => {
        const ringIndex = index < INNER_RING_COUNT ? 0 : 1;
        const onRing = ringIndex === 0 ? INNER_RING_COUNT : kpis.length - INNER_RING_COUNT;
        const slot = ringIndex === 0 ? index : index - INNER_RING_COUNT;
        const angle = (slot / onRing) * Math.PI * 2 + ringIndex * 0.4;
        const radius = rings[ringIndex].radius;

        const holder = new THREE.Group();
        holder.position.set(Math.cos(angle) * radius, 0, Math.sin(angle) * radius);

        const capsule = new THREE.Mesh(capsuleGeometry, new THREE.MeshPhysicalMaterial({
            color: CATEGORY_COLORS[kpi.category] ?? 0x64748b,
            roughness: 0.18, metalness: 0.05, clearcoat: 1, clearcoatRoughness: 0.1,
            transparent: true, opacity: 0.9,
        }));
        capsule.rotation.z = 0.9;
        capsule.userData.kpi = kpi;
        holder.add(capsule);

        const status = STATUS[kpi.status] ?? STATUS.na;
        const ring = new THREE.Mesh(ringGeometry, new THREE.MeshBasicMaterial({
            color: status.color, transparent: true, opacity: kpi.status === 'na' ? 0.35 : 0.9,
            blending: THREE.AdditiveBlending, depthWrite: false,
        }));
        holder.add(ring);

        const statusGlow = new THREE.Sprite(new THREE.SpriteMaterial({
            map: glow, color: status.color, transparent: true, opacity: kpi.status === 'na' ? 0.12 : 0.4,
            depthWrite: false, blending: THREE.AdditiveBlending,
        }));
        statusGlow.scale.set(1.3, 1.3, 1);
        holder.add(statusGlow);

        ringGroups[ringIndex].add(holder);
        return { kpi, holder, capsule, ring, statusGlow, spin: 0.4 + (index % 5) * 0.12, phase: index * 0.7 };
    });

    // ---- Data streams: particles from the core to each capsule ----
    const perStream = small() ? 6 : 12;
    const count = capsules.length * perStream;
    const positions = new Float32Array(count * 3);
    const progress = Float32Array.from({ length: count }, (_, i) => (i % perStream) / perStream + Math.random() * 0.05);
    const particleGeometry = new THREE.BufferGeometry();
    particleGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    const particles = new THREE.Points(particleGeometry, new THREE.PointsMaterial({
        map: glow, color: 0x2dd4bf, size: 0.16, transparent: true, opacity: 0.85,
        depthWrite: false, blending: THREE.AdditiveBlending,
    }));
    system.add(particles);

    // Faint dust far behind, for depth.
    const dustCount = small() ? 80 : 180;
    const dust = new Float32Array(dustCount * 3);
    for (let i = 0; i < dustCount; i++) {
        dust[i * 3] = (Math.random() - 0.5) * 26;
        dust[i * 3 + 1] = (Math.random() - 0.5) * 12;
        dust[i * 3 + 2] = -4 - Math.random() * 8;
    }
    const dustGeometry = new THREE.BufferGeometry();
    dustGeometry.setAttribute('position', new THREE.BufferAttribute(dust, 3));
    scene.add(new THREE.Points(dustGeometry, new THREE.PointsMaterial({ color: 0x94a3b8, size: 0.05, transparent: true, opacity: 0.5 })));

    // ---- Layout: the core sits in the gap between the text and the login card (wide screens), centred when stacked ----
    function resize() {
        const width = container.clientWidth;
        const height = container.clientHeight;
        if (!width || !height) return;   // hidden / not laid out yet
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();

        const visibleWidth = 2 * camera.position.z * Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) * camera.aspect;
        const anchor = small() ? 0.5 : gapAnchor();
        const offset = (anchor - 0.5) * visibleWidth;
        system.position.x = offset;
        label.position.x = offset;
        const scale = small() ? 0.75 : 1;
        system.scale.setScalar(scale);
        label.scale.set(2.2 * scale, 0.55 * scale, 1);

        // setSize() clears the canvas: the reduced-motion still frame must be drawn again.
        if (reducedMotion) renderer.render(scene, camera);
    }
    /** Horizontal centre of the gap between the hero text and the login card, as a fraction of the container. */
    function gapAnchor() {
        const text = document.querySelector('.hero-text');
        const card = document.querySelector('.login-wrap .card');
        if (!text || !card) return 0.5;
        const box = container.getBoundingClientRect();
        const gap = (text.getBoundingClientRect().right + card.getBoundingClientRect().left) / 2;
        // Slightly towards the text, so the core and its label clear the card's shadow.
        return (gap - 24 - box.left) / box.width;
    }

    new ResizeObserver(resize).observe(container);
    resize();

    // ---- Mouse: gentle parallax + hover tooltip ----
    const tooltip = document.getElementById('rduSceneTooltip');
    const pointer = new THREE.Vector2(-10, -10);
    let overForm = false;   // the login card sits above the scene: no tooltips while using it
    const tilt = { x: 0, y: 0 };
    const raycaster = new THREE.Raycaster();
    const capsuleMeshes = capsules.map((c) => c.capsule);
    let pointerMoved = false;
    window.addEventListener('pointermove', (e) => {
        pointerMoved = true;
        const rect = container.getBoundingClientRect();
        pointer.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
        pointer.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
        overForm = !!e.target.closest?.('.card, a, button, input, label');
        tilt.x = pointer.y * 0.08;
        tilt.y = pointer.x * 0.15;
        if (tooltip) {
            tooltip.style.left = `${e.clientX - rect.left + 14}px`;
            tooltip.style.top = `${e.clientY - rect.top + 14}px`;
        }
        if (reducedMotion) updateHover();   // no animation loop to pick it up
    }, { passive: true });

    function updateHover() {
        if (!tooltip) return;
        if (overForm) {
            tooltip.hidden = true;
            return;
        }
        raycaster.setFromCamera(pointer, camera);
        const hit = raycaster.intersectObjects(capsuleMeshes, false)[0];
        if (hit) {
            const { kpi } = hit.object.userData;
            tooltip.textContent = `${kpi.code} · ${kpi.name} · ${(STATUS[kpi.status] ?? STATUS.na).label}`;
            tooltip.hidden = false;
        } else {
            tooltip.hidden = true;
        }
    }

    // ---- Animation ----
    const clock = new THREE.Clock();
    const from = new THREE.Vector3();
    const to = new THREE.Vector3();
    const toLocal = new THREE.Matrix4();

    function frame(dt, t) {
        rings.forEach(({ speed }, i) => { ringGroups[i].rotation.y += speed * dt; });
        core.rotation.y += 0.15 * dt;
        shell.rotation.y -= 0.08 * dt;
        shell.rotation.x += 0.05 * dt;
        halo.material.opacity = 0.45 + Math.sin(t * 1.4) * 0.1;
        system.rotation.x += (0.5 + tilt.x - system.rotation.x) * 0.05;
        system.rotation.y += (tilt.y - system.rotation.y) * 0.05;

        capsules.forEach((c) => {
            c.capsule.rotation.y += c.spin * dt;
            c.holder.position.y = Math.sin(t * 0.8 + c.phase) * 0.12;
            c.ring.lookAt(camera.position);
            if (c.kpi.status === 'fail') {
                // A failing KPI breathes slowly, to draw the eye without flashing.
                const pulse = 0.55 + (Math.sin(t * 2.2 + c.phase) + 1) * 0.225;
                c.ring.material.opacity = pulse;
                c.statusGlow.material.opacity = pulse * 0.55;
            }
        });

        system.updateMatrixWorld();
        toLocal.copy(system.matrixWorld).invert();
        capsules.forEach((c, ci) => {
            c.holder.getWorldPosition(to).applyMatrix4(toLocal);
            for (let k = 0; k < perStream; k++) {
                const i = ci * perStream + k;
                progress[i] = (progress[i] + dt * 0.28) % 1;
                const p = progress[i];
                from.set(0, 0, 0).lerp(to, p);
                from.y += Math.sin(p * Math.PI) * 0.35;   // a slight arc
                positions[i * 3] = from.x;
                positions[i * 3 + 1] = from.y;
                positions[i * 3 + 2] = from.z;
            }
        });
        particleGeometry.attributes.position.needsUpdate = true;
    }

    container.classList.add('is-ready');
    container.closest('.hero')?.classList.add('has-scene');

    if (reducedMotion) {
        frame(0, 0);
        renderer.render(scene, camera);
        return;
    }

    // Exactly one animation loop: stopped while the tab is hidden, restarted when it shows again.
    let rafId = null;
    function loop() {
        const dt = Math.min(clock.getDelta(), 0.05);
        frame(dt, clock.elapsedTime);
        // Re-check while a tooltip shows too: its capsule keeps orbiting away from a still pointer.
        if (pointerMoved || (tooltip && !tooltip.hidden)) {
            updateHover();
            pointerMoved = false;
        }
        renderer.render(scene, camera);
        rafId = requestAnimationFrame(loop);
    }
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            cancelAnimationFrame(rafId);
            rafId = null;
        } else if (rafId === null) {
            clock.getDelta();   // don't jump by the time spent hidden
            rafId = requestAnimationFrame(loop);
        }
    });
    rafId = requestAnimationFrame(loop);
}

const container = document.getElementById('rduScene');
const dataEl = document.getElementById('rduSceneData');
if (container && dataEl && webglAvailable()) {
    try {
        start(container, JSON.parse(dataEl.textContent).kpis ?? []);
    } catch (e) {
        // Keep the static background; the login form never depends on the scene.
        console.warn('RDU scene disabled:', e);
    }
}
