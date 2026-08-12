document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('themeToggle');
  if (toggle) {
    toggle.addEventListener('click', () => {
      const current = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = current;
      document.cookie = `theme=${current};path=/;max-age=31536000`;
    });
  }

  const menuToggle = document.getElementById('menuToggle');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');
  const setMenuOpen = (open) => {
    document.body.classList.toggle('sidebar-open', open);
    if (menuToggle) {
      menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
  };
  if (menuToggle) {
    menuToggle.addEventListener('click', () => setMenuOpen(!document.body.classList.contains('sidebar-open')));
  }
  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', () => setMenuOpen(false));
  }
  document.querySelectorAll('.sidebar nav a').forEach((link) => {
    link.addEventListener('click', () => setMenuOpen(false));
  });

  const patientSearch = document.querySelector('input[name="q"]');
  const lookup = document.getElementById('patientLookup');
  let timer;
  if (patientSearch && lookup) {
    patientSearch.addEventListener('input', () => {
      clearTimeout(timer);
      const q = patientSearch.value.trim();
      if (q.length < 2) {
        lookup.innerHTML = '';
        return;
      }
      timer = setTimeout(async () => {
        const token = sessionStorage.getItem('apiToken');
        const res = await fetch(`../api/index.php?path=patients/lookup&q=${encodeURIComponent(q)}`, {
          headers: token ? { Authorization: `Bearer ${token}` } : {}
        });
        if (!res.ok) return;
        const json = await res.json();
        lookup.innerHTML = json.data.map(p => `<a href="emr.php?patient=${p.id}"><strong>${p.patient_no}</strong> ${p.first_name} ${p.last_name} <span>${p.phone || ''} ${p.blood_group ? `| Blood: ${p.blood_group}` : ''} ${p.genotype ? `| Genotype: ${p.genotype}` : ''}</span></a>`).join('');
      }, 250);
    });
  }

  const canvas = document.getElementById('revenueChart');
  if (canvas) {
    const ctx = canvas.getContext('2d');
    const tooltip = document.getElementById('revenueTooltip');
    let points = [];
    try {
      points = JSON.parse(canvas.dataset.values || '[]');
    } catch (error) {
      points = [];
    }
    const formatMoney = (value) => `NGN ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const roundedRect = (context, x, y, width, height, radius) => {
      const r = Math.min(radius, Math.abs(width) / 2, Math.abs(height) / 2);
      context.beginPath();
      context.moveTo(x + r, y);
      context.lineTo(x + width - r, y);
      context.quadraticCurveTo(x + width, y, x + width, y + r);
      context.lineTo(x + width, y + height);
      context.lineTo(x, y + height);
      context.lineTo(x, y + r);
      context.quadraticCurveTo(x, y, x + r, y);
      context.closePath();
    };
    let hitAreas = [];

    const drawChart = (activeIndex = -1) => {
      const dpr = window.devicePixelRatio || 1;
      const rect = canvas.getBoundingClientRect();
      const width = Math.max(320, Math.floor(rect.width || 640));
      const height = Number(canvas.getAttribute('height') || 260);
      canvas.width = width * dpr;
      canvas.height = height * dpr;
      canvas.style.height = `${height}px`;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx.clearRect(0, 0, width, height);

      const styles = getComputedStyle(document.documentElement);
      const primary = styles.getPropertyValue('--primary').trim() || '#2f9e68';
      const accent = styles.getPropertyValue('--accent').trim() || '#86cfa3';
      const muted = styles.getPropertyValue('--muted').trim() || '#5f7f70';
      const line = styles.getPropertyValue('--line').trim() || '#d7eadf';
      const values = points.map((point) => Number(point.value || 0));
      const max = Math.max(...values, 1);
      const pad = { top: 24, right: 18, bottom: 42, left: 48 };
      const chartW = width - pad.left - pad.right;
      const chartH = height - pad.top - pad.bottom;
      const gap = chartW / Math.max(points.length, 1);
      const barW = Math.min(46, gap * 0.62);

      ctx.font = '12px Segoe UI, Arial';
      ctx.textBaseline = 'middle';
      ctx.strokeStyle = line;
      ctx.lineWidth = 1;
      for (let i = 0; i <= 4; i++) {
        const y = pad.top + (chartH / 4) * i;
        ctx.beginPath();
        ctx.moveTo(pad.left, y);
        ctx.lineTo(width - pad.right, y);
        ctx.stroke();
        const labelValue = max - (max / 4) * i;
        ctx.fillStyle = muted;
        ctx.fillText(labelValue === 0 ? '0' : Math.round(labelValue).toLocaleString(), 6, y);
      }

      hitAreas = [];
      const linePoints = [];
      values.forEach((value, index) => {
        const x = pad.left + gap * index + gap / 2;
        const barH = (value / max) * chartH;
        const y = pad.top + chartH - barH;
        const radius = 10;
        const gradient = ctx.createLinearGradient(0, y, 0, pad.top + chartH);
        gradient.addColorStop(0, activeIndex === index ? accent : primary);
        gradient.addColorStop(0.58, activeIndex === index ? primary : '#54c987');
        gradient.addColorStop(1, 'rgba(47,158,104,0.16)');
        ctx.fillStyle = 'rgba(20,83,45,0.08)';
        roundedRect(ctx, x - barW / 2 + 6, y + 5, barW, barH || 4, radius);
        ctx.fill();
        ctx.fillStyle = gradient;
        roundedRect(ctx, x - barW / 2, y, barW, barH || 4, radius);
        ctx.fill();
        if (value > 0) {
          ctx.fillStyle = activeIndex === index ? '#14532d' : primary;
          ctx.font = '700 11px Segoe UI, Arial';
          ctx.textAlign = 'center';
          ctx.fillText(formatMoney(value).replace('NGN ', ''), x, Math.max(12, y - 10));
        }
        ctx.fillStyle = muted;
        ctx.font = '12px Segoe UI, Arial';
        ctx.textAlign = 'center';
        ctx.fillText(points[index].label, x, height - 18);
        linePoints.push({ x, y, value, label: points[index].label });
        hitAreas.push({ x: x - barW / 2, y: pad.top, w: barW, h: chartH, point: linePoints[index], index });
      });

      linePoints.forEach((point, index) => {
        ctx.fillStyle = activeIndex === index ? '#14532d' : '#ffffff';
        ctx.strokeStyle = activeIndex === index ? '#14532d' : primary;
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(point.x, point.y, activeIndex === index ? 5 : 3, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
      });

      if (values.every((value) => value === 0)) {
        ctx.textAlign = 'center';
        ctx.fillStyle = muted;
        ctx.font = '14px Segoe UI, Arial';
        ctx.fillText('No revenue recorded in the last 7 days', width / 2, height / 2);
      }
    };

    drawChart();
    window.addEventListener('resize', () => drawChart());
    canvas.addEventListener('mousemove', (event) => {
      const rect = canvas.getBoundingClientRect();
      const x = event.clientX - rect.left;
      const y = event.clientY - rect.top;
      const hit = hitAreas.find((area) => x >= area.x && x <= area.x + area.w && y >= area.y && y <= area.y + area.h);
      if (!hit) {
        if (tooltip) tooltip.classList.remove('visible');
        drawChart();
        return;
      }
      drawChart(hit.index);
      if (tooltip) {
        tooltip.innerHTML = `<strong>${hit.point.label}</strong><span>${formatMoney(hit.point.value)}</span>`;
        tooltip.style.left = `${Math.min(rect.width - 120, Math.max(8, hit.point.x - 54))}px`;
        tooltip.style.top = `${Math.max(8, hit.point.y - 58)}px`;
        tooltip.classList.add('visible');
      }
    });
    canvas.addEventListener('mouseleave', () => {
      if (tooltip) tooltip.classList.remove('visible');
      drawChart();
    });
  }

  document.querySelectorAll('.queue-card').forEach((card) => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.queue-card').forEach((item) => item.classList.remove('active'));
      card.classList.add('active');
      const select = document.getElementById('visitSelect');
      if (select) {
        select.value = card.dataset.visit;
        select.dispatchEvent(new Event('change'));
      }
    });
  });

  const labTemplate = document.getElementById('labTestTemplate');
  if (labTemplate) {
    labTemplate.addEventListener('change', () => {
      const [name, sample, price, range] = labTemplate.value.split('|');
      if (!name) return;
      document.getElementById('labTestName').value = name === 'Custom Test' ? '' : name;
      document.getElementById('labSampleType').value = sample || '';
      document.getElementById('labTestPrice').value = price || '';
      document.getElementById('labNormalRange').value = range || '';
    });
  }
});
