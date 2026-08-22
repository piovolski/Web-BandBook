(() => {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const parseJsonScript = (selector) => {
    const node = $(selector);
    if (!node) return null;
    try { return JSON.parse(node.textContent); } catch { return null; }
  };
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[char]));
  const signed = (value) => Number(value) > 0 ? `+${value}` : String(value);

  $$('[data-filter-list]').forEach(input => {
    input.addEventListener('input', () => {
      const list = $(input.dataset.filterList);
      if (!list) return;
      const query = input.value.trim().toLocaleLowerCase('pl');
      $$('[data-filter-text]', list).forEach(item => item.hidden = !item.dataset.filterText.includes(query));
    });
  });

  $$('[data-copy-value]').forEach(button => {
    button.addEventListener('click', async () => {
      const raw = button.dataset.copyValue;
      const absolute = new URL(raw, window.location.href).href;
      try {
        await navigator.clipboard.writeText(absolute);
        const old = button.textContent;
        button.textContent = 'Skopiowano ✓';
        setTimeout(() => button.textContent = old, 1800);
      } catch {
        window.prompt('Skopiuj adres:', absolute);
      }
    });
  });

  $$('[data-step]').forEach(button => {
    button.addEventListener('click', () => {
      const input = $('input', button.parentElement);
      if (!input) return;
      input.value = String(Number(input.value || 0) + Number(button.dataset.step));
      input.dispatchEvent(new Event('change', {bubbles: true}));
    });
  });

  const songEditor = $('[data-song-editor]');
  if (songEditor) initSongEditor(songEditor);

  function initSongEditor(root) {
    let sections = parseJsonScript('[data-initial-sections]') || [];
    let form = parseJsonScript('[data-initial-form]') || [];
    let counter = Date.now();
    const sectionsContainer = $('[data-sections-container]', root);
    const palette = $('[data-form-palette]', root);
    const sequence = $('[data-form-sequence]', root);

    if (!sections.length) {
      sections.push({key: `new-${counter++}`, type: 'chorus', label: 'Refren', lyrics: '', chords: '', comment: ''});
    }

    const readSections = () => {
      $$('.section-editor-card', sectionsContainer).forEach(card => {
        const section = sections.find(item => item.key === card.dataset.key);
        if (!section) return;
        section.type = $('[data-field="type"]', card).value;
        section.label = $('[data-field="label"]', card).value;
        section.lyrics = $('[data-field="lyrics"]', card).value;
        section.chords = $('[data-field="chords"]', card).value;
        section.comment = $('[data-field="comment"]', card).value;
      });
    };

    const sync = () => {
      readSections();
      $('[data-sections-json]', root).value = JSON.stringify(sections);
      $('[data-form-json]', root).value = JSON.stringify(form);
    };

    const renderSections = () => {
      sectionsContainer.innerHTML = '';
      sections.forEach((section, index) => {
        const card = document.createElement('article');
        card.className = 'section-editor-card';
        card.dataset.key = section.key;
        card.innerHTML = `
          <div class="section-card-head">
            <span class="drag-handle">${index + 1}</span>
            <select data-field="type" aria-label="Typ części">
              <option value="verse">Zwrotka</option><option value="chorus">Refren</option><option value="bridge">Bridge</option>
              <option value="intro">Intro</option><option value="outro">Outro</option><option value="instrumental">Instrumentalna</option><option value="custom">Inna</option>
            </select>
            <input data-field="label" aria-label="Nazwa części" placeholder="Nazwa części">
            <div class="mini-actions"><button type="button" data-move="-1" title="Przenieś wyżej">↑</button><button type="button" data-move="1" title="Przenieś niżej">↓</button><button type="button" data-remove title="Usuń">×</button></div>
          </div>
          <div class="lyrics-chords-grid">
            <label><span>Tekst</span><textarea data-field="lyrics" rows="6" placeholder="Jedna linia tekstu…"></textarea></label>
            <label><span>Chwyty</span><textarea class="chord-input" data-field="chords" rows="6" placeholder="D A e h"></textarea></label>
          </div>
          <div class="section-card-foot"><label>Komentarz<input data-field="comment" placeholder="Opcjonalna wskazówka dla tej części"></label><button class="text-action" type="button" data-paste-pairs>Wklej tekst + chwyty</button></div>`;
        $('[data-field="type"]', card).value = section.type || 'verse';
        $('[data-field="label"]', card).value = section.label || '';
        $('[data-field="lyrics"]', card).value = section.lyrics || '';
        $('[data-field="chords"]', card).value = section.chords || '';
        $('[data-field="comment"]', card).value = section.comment || '';
        sectionsContainer.append(card);
      });
      renderForm();
      sync();
    };

    const renderForm = () => {
      readSections();
      palette.innerHTML = sections.map(section => `<button type="button" data-add-to-form="${escapeHtml(section.key)}">+ ${escapeHtml(section.label || 'Bez nazwy')}</button>`).join('');
      if (!form.length) {
        sequence.innerHTML = '<div class="form-empty">Kliknij część powyżej, aby zbudować formę.</div>';
      } else {
        sequence.innerHTML = form.map((item, index) => {
          const section = sections.find(candidate => candidate.key === item.sectionKey);
          return `<article class="form-chip" data-form-index="${index}"><span class="form-order">${index + 1}</span><strong>${escapeHtml(section?.label || 'Usunięta część')}</strong><label>Transp. <input type="number" min="-24" max="24" value="${Number(item.transpose || 0)}" data-form-transpose></label><input class="form-comment-input" value="${escapeHtml(item.comment || '')}" placeholder="Komentarz" data-form-comment><div class="mini-actions"><button type="button" data-form-move="-1">↑</button><button type="button" data-form-move="1">↓</button><button type="button" data-form-clone title="Powtórz">⧉</button><button type="button" data-form-remove>×</button></div></article>`;
        }).join('');
      }
      sync();
    };

    root.addEventListener('input', event => {
      if (event.target.matches('[data-field]')) {
        readSections();
        if (event.target.matches('[data-field="label"]')) renderForm();
      }
      if (event.target.matches('[data-form-transpose], [data-form-comment]')) {
        const index = Number(event.target.closest('[data-form-index]').dataset.formIndex);
        if (event.target.matches('[data-form-transpose]')) form[index].transpose = Number(event.target.value || 0);
        else form[index].comment = event.target.value;
        sync();
      }
    });

    root.addEventListener('click', event => {
      const target = event.target;
      if (target.matches('[data-add-section]')) {
        readSections();
        sections.push({key: `new-${counter++}`, type: 'verse', label: `Zwrotka ${sections.filter(item => item.type === 'verse').length + 1}`, lyrics: '', chords: '', comment: ''});
        renderSections();
      }
      const sectionCard = target.closest('.section-editor-card');
      if (sectionCard && target.matches('[data-remove]')) {
        readSections();
        const key = sectionCard.dataset.key;
        if (sections.length === 1) return alert('Pieśń musi mieć co najmniej jedną część.');
        sections = sections.filter(item => item.key !== key);
        form = form.filter(item => item.sectionKey !== key);
        renderSections();
      }
      if (sectionCard && target.matches('[data-move]')) {
        readSections();
        const index = sections.findIndex(item => item.key === sectionCard.dataset.key);
        const next = index + Number(target.dataset.move);
        if (next >= 0 && next < sections.length) [sections[index], sections[next]] = [sections[next], sections[index]];
        renderSections();
      }
      if (sectionCard && target.matches('[data-paste-pairs]')) {
        const pasted = window.prompt('Wklej wiersze w formacie: tekst [TAB] chwyty');
        if (!pasted) return;
        const rows = pasted.split(/\r?\n/).map(line => {
          const cells = line.split('\t');
          return {lyrics: cells.shift() || '', chords: cells.join(' ').trim()};
        });
        $('[data-field="lyrics"]', sectionCard).value = rows.map(row => row.lyrics).join('\n');
        $('[data-field="chords"]', sectionCard).value = rows.map(row => row.chords).join('\n');
        readSections();
      }
      if (target.matches('[data-add-to-form]')) {
        form.push({sectionKey: target.dataset.addToForm, transpose: 0, comment: ''});
        renderForm();
      }
      const formCard = target.closest('[data-form-index]');
      if (formCard) {
        const index = Number(formCard.dataset.formIndex);
        if (target.matches('[data-form-remove]')) form.splice(index, 1);
        if (target.matches('[data-form-clone]')) form.splice(index + 1, 0, {...form[index]});
        if (target.matches('[data-form-move]')) {
          const next = index + Number(target.dataset.formMove);
          if (next >= 0 && next < form.length) [form[index], form[next]] = [form[next], form[index]];
        }
        if (target.matches('[data-form-remove], [data-form-clone], [data-form-move]')) renderForm();
      }
    });

    root.addEventListener('submit', sync);
    renderSections();
  }

  const eventSongEditor = $('[data-event-song-editor]');
  if (eventSongEditor) initEventSongEditor(eventSongEditor);

  function initEventSongEditor(root) {
    let form = parseJsonScript('[data-event-initial-form]') || [];
    const sections = parseJsonScript('[data-event-sections]') || [];
    const palette = $('[data-event-form-palette]', root);
    const list = $('[data-event-form-list]', root);
    const hidden = $('[data-event-form-json]', root);

    const sync = () => hidden.value = JSON.stringify(form);
    const sourceSection = item => sections.find(section => section.id === Number(item.sectionId));
    const effective = (item, field) => {
      const override = item[`${field}Override`];
      if (override !== null && override !== undefined) return String(override);
      const ownSource = item[`source${field[0].toUpperCase()}${field.slice(1)}`];
      if (ownSource !== null && ownSource !== undefined) return String(ownSource);
      const section = sourceSection(item);
      return String(section?.[field] ?? '');
    };
    const render = () => {
      palette.innerHTML = sections.map(section => `<button type="button" data-event-add="${section.id}">+ ${escapeHtml(section.label)}</button>`).join('');
      list.innerHTML = form.length ? form.map((item, index) => {
        const label = effective(item, 'label') || 'Część';
        const lyrics = effective(item, 'lyrics');
        const chords = effective(item, 'chords');
        const isCustom = ['labelOverride', 'lyricsOverride', 'chordsOverride'].some(field => item[field] !== null && item[field] !== undefined);
        return `<article class="event-form-item" data-event-form-index="${index}">
          <span class="form-order">${index + 1}</span>
          <div class="event-form-name"><strong data-event-part-heading>${escapeHtml(label)}</strong><small>${isCustom ? 'Wersja zmieniona dla wydarzenia' : 'Wystąpienie w formie'}</small></div>
          <label>Transpozycja<input type="number" min="-24" max="24" value="${Number(item.transpose || 0)}" data-event-transpose></label>
          <label class="event-form-comment">Komentarz<input value="${escapeHtml(item.comment || '')}" placeholder="Opcjonalnie" data-event-comment></label>
          <div class="mini-actions"><button type="button" data-event-move="-1" title="W górę">↑</button><button type="button" data-event-move="1" title="W dół">↓</button><button type="button" data-event-clone title="Powtórz">⧉</button><button type="button" data-event-remove title="Usuń">×</button></div>
          <details class="event-part-editor"><summary>Edytuj tekst i chwyty tej części</summary>
            <div class="event-part-editor-body">
              <label>Nazwa części<input value="${escapeHtml(label)}" data-event-part-label></label>
              <div class="lyrics-chords-grid">
                <label>Tekst<textarea rows="7" data-event-part-lyrics>${escapeHtml(lyrics)}</textarea></label>
                <label>Chwyty<textarea rows="7" data-event-part-chords>${escapeHtml(chords)}</textarea></label>
              </div>
              <div class="event-part-editor-foot"><small>Zmiany dotyczą wyłącznie tego wystąpienia części w tym wydarzeniu.</small><button class="button button-ghost" type="button" data-event-part-reset>Przywróć wersję źródłową</button></div>
            </div>
          </details>
        </article>`;
      }).join('') : '<div class="form-empty">Forma jest pusta. Dodaj część powyżej.</div>';
      sync();
    };

    root.addEventListener('click', event => {
      const target = event.target;
      if (target.matches('[data-event-add]')) {
        const section = sections.find(item => item.id === Number(target.dataset.eventAdd));
        if (section) form.push({
          sectionId: section.id,
          sourceLabel: section.label,
          sourceLyrics: section.lyrics,
          sourceChords: section.chords,
          labelOverride: null,
          lyricsOverride: null,
          chordsOverride: null,
          transpose: 0,
          comment: '',
        });
        render();
      }
      const card = target.closest('[data-event-form-index]');
      if (!card) return;
      const index = Number(card.dataset.eventFormIndex);
      if (target.matches('[data-event-remove]')) form.splice(index, 1);
      if (target.matches('[data-event-clone]')) form.splice(index + 1, 0, {...form[index], id: null});
      if (target.matches('[data-event-part-reset]')) {
        form[index].labelOverride = null;
        form[index].lyricsOverride = null;
        form[index].chordsOverride = null;
      }
      if (target.matches('[data-event-move]')) {
        const next = index + Number(target.dataset.eventMove);
        if (next >= 0 && next < form.length) [form[index], form[next]] = [form[next], form[index]];
      }
      if (target.matches('[data-event-remove], [data-event-clone], [data-event-move], [data-event-part-reset]')) render();
    });
    root.addEventListener('input', event => {
      const card = event.target.closest('[data-event-form-index]');
      if (!card) return;
      const item = form[Number(card.dataset.eventFormIndex)];
      if (event.target.matches('[data-event-transpose]')) item.transpose = Number(event.target.value || 0);
      if (event.target.matches('[data-event-comment]')) item.comment = event.target.value;
      if (event.target.matches('[data-event-part-label]')) {
        item.labelOverride = event.target.value;
        const heading = $('[data-event-part-heading]', card);
        if (heading) heading.textContent = event.target.value || 'Część';
      }
      if (event.target.matches('[data-event-part-lyrics]')) item.lyricsOverride = event.target.value;
      if (event.target.matches('[data-event-part-chords]')) item.chordsOverride = event.target.value;
      sync();
    });
    root.addEventListener('submit', sync);
    render();
  }

  const songBrowser = $('[data-song-browser]');
  if (songBrowser) initSongBrowser(songBrowser);

  function initSongBrowser(root) {
    const songs = parseJsonScript('[data-song-browser-data]') || [];
    const results = $('[data-browser-results]', root);
    const preview = $('[data-song-preview]', root);
    const resultCount = $('[data-browser-result-count]', root);
    const more = $('[data-browser-more]', root);
    const search = $('[data-browser-search]', root);
    const repertoire = $('[data-repertoire-list]', root);
    const repertoireCount = $('[data-repertoire-count]', root);
    const repertoireEmpty = $('[data-repertoire-empty]', root);
    const storageKey = `bandbook-browser:${root.dataset.addApi}`;
    const batchSize = 60;
    let selectedCategory = 'all';
    let selectedSongId = null;
    let visibleLimit = batchSize;
    let loadObserver = null;
    let loadingMore = false;

    const normalize = value => String(value || '')
      .toLocaleLowerCase('pl')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, ' ')
      .trim();
    const songSearchText = song => normalize([
      song.title,
      song.alt_title,
      song.first_lyrics,
      song.authors,
      ...(song.categories || []).map(category => category.name),
    ].join(' '));
    songs.forEach(song => { song.searchText = songSearchText(song); });

    try {
      const saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
      search.value = saved.query || '';
      selectedCategory = saved.category || 'all';
    } catch {}

    const categoryPosition = (song, categoryId) => {
      const category = (song.categories || []).find(item => String(item.id) === String(categoryId));
      return category ? Number(category.position) : Number.MAX_SAFE_INTEGER;
    };

    const filteredSongs = () => {
      const query = normalize(search.value);
      const filtered = songs.filter(song => {
        if (query && !song.searchText.includes(query)) return false;
        if (selectedCategory === 'with-chords' && !song.has_chords) return false;
        if (selectedCategory === 'without-chords' && song.has_chords) return false;
        if (!['all', 'with-chords', 'without-chords'].includes(selectedCategory)
          && !(song.categories || []).some(category => String(category.id) === selectedCategory)) return false;
        return true;
      });
      if (!['all', 'with-chords', 'without-chords'].includes(selectedCategory)) {
        filtered.sort((a, b) => categoryPosition(a, selectedCategory) - categoryPosition(b, selectedCategory));
      } else {
        filtered.sort((a, b) => String(a.title).localeCompare(String(b.title), 'pl'));
      }
      return filtered;
    };

    const songRow = song => {
      const categories = (song.categories || []).filter(item => item.group !== 'source').slice(0, 2);
      const used = Number(song.event_uses) > 0 ? `<span class="browser-used">W repertuarze${song.event_uses > 1 ? ` ×${song.event_uses}` : ''}</span>` : '';
      const selected = Number(song.id) === Number(selectedSongId) ? 'selected' : '';
      const firstLine = String(song.first_lyrics || '').split(/\r?\n/).find(Boolean) || 'Brak podglądu tekstu';
      return `<article class="browser-song-row ${selected}" data-browser-song-row="${song.id}">
        <button class="browser-song-open" type="button" data-preview-song="${song.id}">
          <span class="browser-song-key">${escapeHtml(song.source_key || '—')}</span>
          <span class="browser-song-copy"><strong>${escapeHtml(song.title)}</strong><small>${escapeHtml(song.authors || song.alt_title || firstLine)}</small><span class="browser-tags">${categories.map(item => `<i>${escapeHtml(item.name)}</i>`).join('')}${song.has_chords ? '<i class="has-chords">chwyty</i>' : '<i>tekst</i>'}${used}</span></span>
        </button>
        <button class="quick-add" type="button" data-add-song="${song.id}" title="Dodaj do repertuaru">+</button>
      </article>`;
    };

    const loadNextBatch = () => {
      if (loadingMore) return;
      const total = filteredSongs().length;
      if (visibleLimit >= total) return;
      loadingMore = true;
      visibleLimit = Math.min(visibleLimit + batchSize, total);
      renderResults({preserveScroll: true});
      requestAnimationFrame(() => { loadingMore = false; });
    };

    const observeListEnd = () => {
      loadObserver?.disconnect();
      const sentinel = $('[data-browser-load-sentinel]', results);
      if (!sentinel || !('IntersectionObserver' in window)) return;
      loadObserver = new IntersectionObserver(entries => {
        if (entries.some(entry => entry.isIntersecting)) loadNextBatch();
      }, {root: results, rootMargin: '320px 0px'});
      loadObserver.observe(sentinel);
    };

    const renderResults = ({reset = false, preserveScroll = true} = {}) => {
      const previousScroll = results.scrollTop;
      if (reset) visibleLimit = batchSize;
      const filtered = filteredSongs();
      const visible = filtered.slice(0, visibleLimit);
      const hasMore = visible.length < filtered.length;
      resultCount.textContent = filtered.length === 1 ? '1 pieśń' : `${filtered.length} pieśni`;
      results.innerHTML = visible.length
        ? visible.map(songRow).join('') + (hasMore ? '<div class="browser-load-sentinel" data-browser-load-sentinel><span>◌</span> Wczytuję kolejne pieśni…</div>' : '')
        : '<div class="browser-empty"><span>⌕</span><h3>Brak wyników</h3><p>Zmień kategorię lub wpisz inne słowo.</p></div>';
      if (reset) results.scrollTop = 0;
      else if (preserveScroll) results.scrollTop = previousScroll;
      more.hidden = !hasMore;
      more.textContent = hasMore ? `Wyświetlono ${visible.length} z ${filtered.length} · przewiń w dół, aby wczytać kolejne` : '';
      observeListEnd();
      try { sessionStorage.setItem(storageKey, JSON.stringify({query: search.value, category: selectedCategory})); } catch {}
    };

    const previewPart = (section, index) => {
      const lyricLines = String(section.lyrics || '').split(/\r?\n/);
      const chordLines = String(section.chords || '').split(/\r?\n/);
      const lines = lyricLines.map((line, lineIndex) => `<div class="preview-line"><span>${escapeHtml(chordLines[lineIndex] || '') || '&nbsp;'}</span><p>${escapeHtml(line) || '&nbsp;'}</p></div>`).join('');
      return `<section class="preview-part"><header><b>${index + 1}</b><strong>${escapeHtml(section.label)}</strong><small>${escapeHtml(section.type)}</small></header><div class="preview-lines">${lines}</div>${section.comment ? `<p class="preview-comment">${escapeHtml(section.comment)}</p>` : ''}</section>`;
    };

    const loadPreview = async songId => {
      selectedSongId = Number(songId);
      renderResults();
      preview.innerHTML = '<div class="preview-placeholder"><span class="preview-spinner">◌</span><h3>Wczytywanie…</h3></div>';
      try {
        const url = new URL(root.dataset.previewApi, window.location.href);
        url.searchParams.set('id', String(songId));
        const response = await fetch(url);
        if (!response.ok) throw new Error();
        const {song} = await response.json();
        const sections = new Map((song.sections || []).map(section => [Number(section.id), section]));
        const formSections = (song.form || []).map(item => sections.get(Number(item.section_id))).filter(Boolean);
        const ordered = formSections.length ? formSections : (song.sections || []);
        const badges = (song.categories || []).map(item => `<span>${escapeHtml(item.name)}</span>`).join('');
        const editUrl = new URL(root.dataset.songEditUrl, window.location.href);
        editUrl.searchParams.set('id', String(song.id));
        preview.innerHTML = `<div class="preview-head"><div><p class="eyebrow">Podgląd pieśni</p><h2>${escapeHtml(song.title)}</h2><p>${escapeHtml(song.authors || song.alt_title || '')}</p></div><div class="preview-actions"><a class="button button-ghost" href="${escapeHtml(editUrl.href)}" target="_blank" rel="noopener">Edytuj ↗</a><button class="button button-primary" type="button" data-add-song="${song.id}">Dodaj</button></div></div>
          <div class="preview-meta"><span>Tonacja <b>${escapeHtml(song.source_key || '—')}</b></span><span>Tempo <b>${escapeHtml(song.bpm || '—')} BPM</b></span><span>Forma <b>${ordered.length} części</b></span></div>
          <div class="preview-badges">${badges}</div>
          <div class="preview-form">${ordered.map(previewPart).join('')}</div>`;
      } catch {
        preview.innerHTML = '<div class="preview-placeholder"><span>!</span><h3>Nie udało się wczytać pieśni</h3><p>Spróbuj ponownie.</p></div>';
      }
    };

    const refreshRepertoireControls = () => {
      const items = $$('[data-repertoire-item]', repertoire);
      items.forEach((item, index) => {
        $('.repertoire-number', item).textContent = String(index + 1);
        const up = $('[data-move-up]', item);
        const down = $('[data-move-down]', item);
        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === items.length - 1;
      });
      repertoireCount.textContent = String(items.length);
      repertoireEmpty.hidden = items.length > 0;
    };

    const notify = message => {
      const node = document.createElement('div');
      node.className = 'flash';
      node.setAttribute('role', 'status');
      node.textContent = message;
      document.body.append(node);
      setTimeout(() => node.remove(), 2200);
    };

    const repertoireOrder = () => $$('[data-repertoire-item]', repertoire)
      .map(item => Number(item.dataset.eventSongId));
    const sameOrder = (left, right) => left.length === right.length
      && left.every((id, index) => id === right[index]);
    const restoreRepertoireOrder = order => {
      const items = new Map($$('[data-repertoire-item]', repertoire)
        .map(item => [Number(item.dataset.eventSongId), item]));
      order.forEach(id => {
        const item = items.get(Number(id));
        if (item) repertoire.append(item);
      });
      refreshRepertoireControls();
    };
    let dragState = null;
    let reorderBusy = false;

    const clearDragState = () => {
      repertoire.classList.remove('is-reordering');
      $$('[data-repertoire-item]', repertoire).forEach(item => item.classList.remove('is-dragging'));
    };

    const saveRepertoireOrder = async previousOrder => {
      const nextOrder = repertoireOrder();
      if (sameOrder(previousOrder, nextOrder)) return;
      reorderBusy = true;
      repertoire.classList.add('is-saving');
      repertoire.setAttribute('aria-busy', 'true');
      try {
        const response = await fetch(root.dataset.reorderApi, {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-CSRF-Token': root.dataset.csrf},
          body: JSON.stringify({event_song_ids: nextOrder}),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Nie udało się zapisać kolejności.');
        notify('Kolejność repertuaru została zapisana.');
      } catch (error) {
        restoreRepertoireOrder(previousOrder);
        window.alert(error.message || 'Nie udało się zapisać kolejności repertuaru.');
      } finally {
        reorderBusy = false;
        repertoire.classList.remove('is-saving');
        repertoire.removeAttribute('aria-busy');
      }
    };

    const addSong = async (songId, button) => {
      const song = songs.find(item => Number(item.id) === Number(songId));
      if (!song) return;
      let allowDuplicate = false;
      if (Number(song.event_uses) > 0) {
        allowDuplicate = window.confirm(`„${song.title}” jest już w repertuarze. Dodać ją ponownie?`);
        if (!allowDuplicate) return;
      }
      const previous = button?.textContent;
      if (button) { button.disabled = true; button.textContent = '…'; }
      try {
        const response = await fetch(root.dataset.addApi, {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-CSRF-Token': root.dataset.csrf},
          body: JSON.stringify({song_id: song.id, allow_duplicate: allowDuplicate}),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Nie udało się dodać pieśni.');
        repertoire.insertAdjacentHTML('beforeend', result.html);
        song.event_uses = Number(song.event_uses) + 1;
        refreshRepertoireControls();
        renderResults();
        notify('Pieśń została dodana do repertuaru.');
      } catch (error) {
        window.alert(error.message || 'Nie udało się dodać pieśni.');
      } finally {
        if (button) { button.disabled = false; button.textContent = previous; }
      }
    };

    root.addEventListener('click', event => {
      const category = event.target.closest('[data-category]');
      if (category) {
        selectedCategory = category.dataset.category;
        $$('[data-category]', root).forEach(button => button.classList.toggle('active', button === category));
        renderResults({reset: true, preserveScroll: false});
        return;
      }
      const open = event.target.closest('[data-preview-song]');
      if (open) { loadPreview(open.dataset.previewSong); return; }
      const add = event.target.closest('[data-add-song]');
      if (add) addSong(add.dataset.addSong, add);
    });
    repertoire.addEventListener('pointerdown', event => {
      const handle = event.target.closest('[data-repertoire-drag]');
      const item = handle?.closest('[data-repertoire-item]');
      if (!item || reorderBusy || event.button !== 0) return;
      event.preventDefault();
      dragState = {item, handle, pointerId: event.pointerId, previousOrder: repertoireOrder()};
      handle.setPointerCapture(event.pointerId);
      item.classList.add('is-dragging');
      repertoire.classList.add('is-reordering');
    });
    repertoire.addEventListener('pointermove', event => {
      if (!dragState || event.pointerId !== dragState.pointerId) return;
      event.preventDefault();
      const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-repertoire-item]');
      if (!target || !repertoire.contains(target)) return;
      if (target === dragState.item) return;
      const bounds = target.getBoundingClientRect();
      const after = event.clientY > bounds.top + bounds.height / 2;
      repertoire.insertBefore(dragState.item, after ? target.nextSibling : target);
      if (event.clientY < 90) window.scrollBy(0, -14);
      else if (event.clientY > window.innerHeight - 90) window.scrollBy(0, 14);
    });
    repertoire.addEventListener('pointerup', event => {
      if (!dragState || event.pointerId !== dragState.pointerId) return;
      event.preventDefault();
      const previousOrder = dragState.previousOrder;
      const bounds = repertoire.getBoundingClientRect();
      const droppedInside = event.clientX >= bounds.left && event.clientX <= bounds.right
        && event.clientY >= bounds.top && event.clientY <= bounds.bottom;
      if (dragState.handle.hasPointerCapture(event.pointerId)) dragState.handle.releasePointerCapture(event.pointerId);
      dragState = null;
      clearDragState();
      if (!droppedInside) {
        restoreRepertoireOrder(previousOrder);
        return;
      }
      refreshRepertoireControls();
      saveRepertoireOrder(previousOrder);
    });
    repertoire.addEventListener('pointercancel', event => {
      if (!dragState || event.pointerId !== dragState.pointerId) return;
      const previousOrder = dragState.previousOrder;
      dragState = null;
      clearDragState();
      restoreRepertoireOrder(previousOrder);
    });
    repertoire.addEventListener('keydown', event => {
      const handle = event.target.closest('[data-repertoire-drag]');
      if (!handle || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
      event.preventDefault();
      const item = handle.closest('[data-repertoire-item]');
      const moveButton = event.key === 'ArrowUp' ? $('[data-move-up]', item) : $('[data-move-down]', item);
      if (moveButton && !moveButton.disabled) moveButton.form?.requestSubmit(moveButton);
    });
    search.addEventListener('input', () => renderResults({reset: true, preserveScroll: false}));
    results.addEventListener('scroll', () => {
      if (results.scrollTop + results.clientHeight >= results.scrollHeight - 320) loadNextBatch();
    }, {passive: true});
    const savedCategory = $(`[data-category="${CSS.escape(selectedCategory)}"]`, root);
    if (savedCategory) {
      $$('[data-category]', root).forEach(button => button.classList.toggle('active', button === savedCategory));
    } else {
      selectedCategory = 'all';
    }
    renderResults({reset: true, preserveScroll: false});
  }

  const liveApp = $('[data-live-app]');
  if (liveApp) initLive(liveApp);

  function initLive(root) {
    let snapshot = parseJsonScript('[data-live-snapshot]');
    let selectedSong = snapshot?.state?.event_song_id || snapshot?.songs?.[0]?.id || null;
    let busy = false;
    const nav = $('[data-live-song-nav]', root);
    const stage = $('[data-live-stage]', root);
    const connection = $('[data-connection]', root);
    const follow = $('[data-follow-current]', root);
    const notation = $('[data-live-notation]', root);
    const outputSwitch = $('[data-live-output-switch]', root);
    const csrf = root.dataset.csrf;

    const request = async (url, options = {}) => {
      const response = await fetch(url, options);
      if (!response.ok) throw new Error('Błąd połączenia');
      return response.json();
    };
    const setConnection = online => {
      connection.classList.toggle('online', online);
      connection.classList.toggle('offline', !online);
      connection.innerHTML = `<i></i> ${online ? 'Połączono' : 'Brak połączenia'}`;
    };
    const songById = id => snapshot.songs.find(song => song.id === Number(id));

    const render = () => {
      $$('[data-output-mode]', outputSwitch).forEach(button => {
        const active = button.dataset.outputMode === (snapshot.state.output_mode || 'text');
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      if (follow.checked && snapshot.state.event_song_id) selectedSong = snapshot.state.event_song_id;
      nav.innerHTML = snapshot.songs.length ? snapshot.songs.map((song, index) => {
        const active = song.id === Number(selectedSong) ? 'active' : '';
        const playing = song.id === snapshot.state.event_song_id ? '<span class="playing-dot" title="Aktualna pieśń"></span>' : '';
        const hasNext = song.form.some(item => item.id === snapshot.state.next_form_id);
        return `<button type="button" class="live-song-link ${active} ${hasNext ? 'has-next' : ''}" data-select-song="${song.id}"><span>${index + 1}</span><strong>${escapeHtml(song.title)}</strong>${playing}<small>${hasNext ? 'NASTĘPNA' : (song.bpm ? `${song.bpm} BPM` : '— BPM')}</small></button>`;
      }).join('') : '<p class="muted light">Repertuar jest pusty.</p>';
      const song = songById(selectedSong);
      if (!song) {
        stage.innerHTML = '<div class="live-empty"><span>♪</span><h2>Dodaj pieśni do repertuaru</h2><p>Pozycje pojawią się tutaj automatycznie.</p></div>';
        return;
      }
      const songEditUrl = new URL(root.dataset.songEditUrl, window.location.href);
      songEditUrl.searchParams.set('id', String(song.song_id));
      stage.innerHTML = `
        <section class="live-song-head">
          <div><p class="eyebrow">Aktualnie otwarta pieśń</p><h2>${escapeHtml(song.title)}</h2><p>${song.source_key ? `Tonacja źródłowa ${escapeHtml(song.source_key)} · ` : ''}${song.form.length} części w formie</p></div>
          <div class="live-song-controls">
            <label>Transpozycja <span class="live-stepper"><button type="button" data-live-delta="-1" data-scope="song" data-id="${song.id}" data-field="transpose_steps">−</button><strong>${signed(song.transpose_steps)}</strong><button type="button" data-live-delta="1" data-scope="song" data-id="${song.id}" data-field="transpose_steps">+</button></span></label>
            <label>Tempo <input type="number" min="20" max="300" value="${song.bpm ?? ''}" data-live-setting data-scope="song" data-id="${song.id}" data-field="bpm_override"> BPM</label>
          </div>
        </section>
        <label class="live-song-note">Komentarz do pieśni<textarea rows="2" placeholder="Brak komentarza" data-live-setting data-scope="song" data-id="${song.id}" data-field="comment">${escapeHtml(song.comment || '')}</textarea></label>
        <details class="live-song-more"><summary>Więcej opcji</summary><div><p>Zmiana pieśni źródłowej wpływa na bibliotekę i wydarzenia bez własnych nadpisań.</p><a class="button button-ghost" href="${escapeHtml(songEditUrl.href)}" target="_blank" rel="noopener">Edytuj pieśń w bibliotece ↗</a></div></details>
        <div class="live-form">${song.form.map((item, index) => renderLiveItem(item, index)).join('')}</div>`;
    };

    const renderLiveItem = (item, index) => {
      const lyrics = String(item.lyrics || '').split(/\r?\n/);
      const chords = String(item.chords || '').split(/\r?\n/);
      const lines = lyrics.map((line, lineIndex) => `<div class="song-line"><div class="chord-line">${escapeHtml(chords[lineIndex] || '') || '&nbsp;'}</div><div class="lyric-line">${escapeHtml(line) || '&nbsp;'}</div></div>`).join('');
      const stateClass = item.id === snapshot.state.current_form_id ? 'is-now' : (item.id === snapshot.state.next_form_id ? 'is-next' : '');
      const badge = item.id === snapshot.state.current_form_id ? '<span class="state-badge now">Teraz</span>' : (item.id === snapshot.state.next_form_id ? '<span class="state-badge next">Następna</span>' : '');
      return `<article class="live-form-card ${stateClass}" data-live-form-id="${item.id}">
        <header><span class="form-order">${index + 1}</span><h3>${escapeHtml(item.label)}</h3>${badge}<button class="live-edit-part" type="button" data-live-edit-part>Edytuj część</button><div class="part-transpose"><button type="button" data-live-delta="-1" data-scope="form" data-id="${item.id}" data-field="transpose_steps">−</button><span>${signed(item.transpose_steps)}</span><button type="button" data-live-delta="1" data-scope="form" data-id="${item.id}" data-field="transpose_steps">+</button></div></header>
        <div class="live-lyrics">${lines}</div>
        <div class="live-part-editor" data-live-part-editor hidden>
          <label>Nazwa części<input value="${escapeHtml(item.label)}" data-live-part-label></label>
          <div class="live-part-fields"><label>Tekst<textarea rows="7" data-live-part-lyrics>${escapeHtml(item.editable_lyrics ?? item.lyrics ?? '')}</textarea></label><label>Chwyty w tonacji bazowej<textarea rows="7" data-live-part-chords>${escapeHtml(item.editable_chords ?? '')}</textarea></label></div>
          <label class="live-source-option"><input type="checkbox" data-live-save-source><span><strong>Zapisz także w pieśni źródłowej</strong><small>Zmiana obejmie bibliotekę i inne wydarzenia bez własnego nadpisania.</small></span></label>
          <div class="live-part-editor-actions"><small>Bez zaznaczenia zmiana dotyczy tylko bieżącego wydarzenia.</small><button class="button button-ghost" type="button" data-live-cancel-part>Anuluj</button><button class="button button-primary" type="button" data-live-save-part>Zapisz część</button></div>
        </div>
        <label class="part-comment">Komentarz<input value="${escapeHtml(item.comment || '')}" placeholder="Brak" data-live-setting data-scope="form" data-id="${item.id}" data-field="comment"></label>
      </article>`;
    };

    const poll = async (force = false) => {
      if (busy) return;
      try {
        const query = new URL(root.dataset.api, window.location.href);
        query.searchParams.set('since', force ? '0' : String(snapshot.revision));
        query.searchParams.set('profile', notation.value);
        const result = await request(query);
        setConnection(true);
        if (!result.unchanged) {
          snapshot = result.snapshot;
          root.dataset.revision = snapshot.revision;
          render();
        }
      } catch { setConnection(false); }
    };

    const saveSetting = async (scope, id, field, value) => {
      busy = true;
      try {
        await request(root.dataset.settingApi, {method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-Token':csrf}, body: JSON.stringify({scope, id, field, value})});
        setConnection(true);
      } catch { setConnection(false); }
      finally { busy = false; await poll(true); }
    };

    const setOutputMode = async mode => {
      if (busy || mode === snapshot.state.output_mode) return;
      busy = true;
      $$('[data-output-mode]', outputSwitch).forEach(button => { button.disabled = true; });
      try {
        await request(root.dataset.outputApi, {
          method: 'POST',
          headers: {'Content-Type':'application/json','X-CSRF-Token':csrf},
          body: JSON.stringify({mode}),
        });
        snapshot.state.output_mode = mode;
        setConnection(true);
        render();
      } catch { setConnection(false); }
      finally {
        busy = false;
        $$('[data-output-mode]', outputSwitch).forEach(button => { button.disabled = false; });
        await poll(true);
      }
    };

    const savePart = async card => {
      const saveToSource = $('[data-live-save-source]', card).checked;
      if (saveToSource && !window.confirm('Zapisać tę część w pieśni źródłowej? Zmiana pojawi się w bibliotece i innych wydarzeniach, które nie mają własnego nadpisania.')) return;
      busy = true;
      try {
        await request(root.dataset.partApi, {
          method: 'POST',
          headers: {'Content-Type':'application/json','X-CSRF-Token':csrf},
          body: JSON.stringify({
            form_id: Number(card.dataset.liveFormId),
            label: $('[data-live-part-label]', card).value,
            lyrics: $('[data-live-part-lyrics]', card).value,
            chords: $('[data-live-part-chords]', card).value,
            save_to_source: saveToSource,
          }),
        });
        setConnection(true);
      } catch { setConnection(false); }
      finally { busy = false; await poll(true); }
    };

    root.addEventListener('click', async event => {
      const outputMode = event.target.closest('[data-output-mode]');
      if (outputMode) {
        await setOutputMode(outputMode.dataset.outputMode);
        return;
      }
      const select = event.target.closest('[data-select-song]');
      if (select) { selectedSong = Number(select.dataset.selectSong); follow.checked = false; render(); return; }
      const delta = event.target.closest('[data-live-delta]');
      if (delta) {
        event.stopPropagation();
        const song = songById(selectedSong);
        let current = 0;
        if (delta.dataset.scope === 'song') current = song.transpose_steps;
        else current = song.form.find(item => item.id === Number(delta.dataset.id))?.transpose_steps || 0;
        await saveSetting(delta.dataset.scope, Number(delta.dataset.id), delta.dataset.field, current + Number(delta.dataset.liveDelta));
        return;
      }
      const editPart = event.target.closest('[data-live-edit-part]');
      if (editPart) {
        event.stopPropagation();
        const editor = $('[data-live-part-editor]', editPart.closest('[data-live-form-id]'));
        editor.hidden = !editor.hidden;
        if (!editor.hidden) $('[data-live-part-label]', editor)?.focus();
        return;
      }
      const cancelPart = event.target.closest('[data-live-cancel-part]');
      if (cancelPart) {
        event.stopPropagation();
        cancelPart.closest('[data-live-part-editor]').hidden = true;
        return;
      }
      const savePartButton = event.target.closest('[data-live-save-part]');
      if (savePartButton) {
        event.stopPropagation();
        await savePart(savePartButton.closest('[data-live-form-id]'));
        return;
      }
      const card = event.target.closest('[data-live-form-id]');
      if (card && !event.target.closest('input, textarea, button, label, [data-live-part-editor]')) {
        busy = true;
        card.classList.add('is-sending');
        try {
          await request(root.dataset.actionApi, {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf}, body:JSON.stringify({form_id:Number(card.dataset.liveFormId)})});
          setConnection(true);
        } catch { setConnection(false); }
        finally { busy = false; await poll(true); }
      }
    });
    root.addEventListener('change', event => {
      const setting = event.target.closest('[data-live-setting]');
      if (setting) saveSetting(setting.dataset.scope, Number(setting.dataset.id), setting.dataset.field, setting.value);
    });
    notation.addEventListener('change', () => poll(true));
    document.addEventListener('keydown', event => {
      if (event.ctrlKey || event.altKey || event.metaKey || event.repeat) return;
      if (event.target.closest('input, textarea, select, [contenteditable="true"]')) return;
      const mode = {b: 'blackout', g: 'background', t: 'text'}[event.key.toLocaleLowerCase('pl')];
      if (mode) {
        event.preventDefault();
        setOutputMode(mode);
      }
    });
    render();
    setInterval(poll, 1000);
  }

  const audienceApp = $('[data-audience-app]');
  if (audienceApp) initAudience(audienceApp);

  function initAudience(root) {
    let snapshot = parseJsonScript('[data-audience-snapshot]');
    const stage = $('[data-audience-stage]', root);
    const next = $('[data-audience-next]', root);
    const connection = $('[data-connection]', root);
    const isOverlay = root.dataset.projectionKind === 'overlay';

    const findItem = id => {
      for (const song of snapshot.songs) {
        const item = song.form.find(part => part.id === Number(id));
        if (item) return {song, item};
      }
      return null;
    };
    const setBackground = () => {
      if (isOverlay || !snapshot.event.background_image || !root.dataset.backgroundApi) {
        root.style.removeProperty('--audience-background');
        return;
      }
      const background = new URL(root.dataset.backgroundApi, window.location.href);
      background.searchParams.set('v', snapshot.event.background_image);
      root.style.setProperty('--audience-background', `url("${background.href}")`);
    };
    const render = () => {
      const current = findItem(snapshot.state.current_form_id);
      const upcoming = findItem(snapshot.state.next_form_id);
      const mode = snapshot.state.output_mode || 'text';
      root.classList.toggle('mode-blackout', mode === 'blackout');
      root.classList.toggle('mode-background', mode === 'background');
      root.classList.toggle('mode-text', mode === 'text');
      setBackground();
      if (isOverlay) {
        stage.innerHTML = mode === 'text' && current
          ? `<article class="audience-content"><p class="eyebrow">${escapeHtml(current.song.title)}</p><h1>${escapeHtml(current.item.label)}</h1><div class="audience-lyrics">${String(current.item.lyrics).split(/\r?\n/).map(line => `<p>${escapeHtml(line) || '&nbsp;'}</p>`).join('')}</div></article>`
          : '';
      } else if (mode === 'text' && current) {
        const lines = String(current.item.lyrics || '').split(/\r?\n/);
        const visibleLines = lines.filter(line => line.trim() !== '').length;
        const sizeClass = visibleLines > 8 ? 'compact' : (visibleLines > 5 ? 'medium' : 'large');
        stage.innerHTML = `<div class="projection-text ${sizeClass}">${lines.map(line => `<p class="projection-line">${escapeHtml(line) || '&nbsp;'}</p>`).join('')}</div>`;
      } else {
        stage.innerHTML = '';
      }
      if (next) next.textContent = upcoming ? `Następna: ${upcoming.song.title} · ${upcoming.item.label}` : '';
    };
    const poll = async () => {
      try {
        const query = new URL(root.dataset.api, window.location.href);
        query.searchParams.set('since', String(snapshot.revision));
        const response = await fetch(query);
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (connection) { connection.className = 'connection online'; connection.innerHTML = '<i></i> Połączono'; }
        if (!result.unchanged) { snapshot = result.snapshot; render(); }
      } catch {
        if (connection) { connection.className = 'connection offline'; connection.innerHTML = '<i></i> Brak połączenia'; }
      }
    };
    render();
    setInterval(poll, 500);
  }
})();
