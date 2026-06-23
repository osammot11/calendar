<script setup>
import { usePlannerContext } from "../../composables/plannerContext";

const { modal, overrideDate, overrideRows, saveOverride } =
    usePlannerContext();
</script>

<template>
    <section class="panel-section surface">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Giorno specifico</p>
                <h2>{{ overrideDate }}</h2>
            </div>
            <button class="button tonal" @click="saveOverride">Applica</button>
        </div>
        <label class="field compact">
            <span>Data</span>
            <input v-model="overrideDate" type="date" />
        </label>
        <div
            v-for="(row, index) in overrideRows"
            :key="index"
            class="time-pair"
        >
            <input v-model="row.start_time" type="time" />
            <input v-model="row.end_time" type="time" />
        </div>
        <button
            class="button text"
            @click="
                overrideRows.push({
                    start_time: '09:00',
                    end_time: '18:00',
                })
            "
        >
            Aggiungi fascia
        </button>
        <button class="button tonal" @click="modal = 'busy'">
            Blocca orario
        </button>
    </section>
</template>
