<!--
  - Copyright (c) Enalean, 2020 - present. All Rights Reserved.
  -
  - This file is a part of Tuleap.
  -
  - Tuleap is free software; you can redistribute it and/or modify
  - it under the terms of the GNU General Public License as published by
  - the Free Software Foundation; either version 2 of the License, or
  - (at your option) any later version.
  -
  - Tuleap is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU General Public License for more details.
  -
  - You should have received a copy of the GNU General Public License
  - along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
    <step-layout previous-step-name="step-1">
        <template v-slot:step_info>
            <step-two-info />
        </template>
        <template v-slot:interactive_content>
            <form
                ref="tracker_creation_form"
                v-on:submit="setCreationFormHasBeenSubmitted"
                method="post"
                id="tracker-creation-form"
                v-bind:enctype="form_enctype"
            >
                <field-csrf-token />
                <field-name />
                <field-shortname />
                <field-description />
                <field-tracker-template-id v-if="is_a_duplication" />
            </form>
        </template>
    </step-layout>
</template>
<script lang="ts">
import Vue from "vue";
import { Mutation, State, Getter } from "vuex-class";
import { Component } from "vue-property-decorator";
import StepLayout from "../layout/StepLayout.vue";
import StepTwoInfo from "./StepTwoInfo.vue";
import FieldName from "./creation-fields/FieldName.vue";
import FieldShortname from "./creation-fields/FieldShortname.vue";
import FieldDescription from "./creation-fields/FieldDescription.vue";
import FieldTrackerTemplateId from "./creation-fields/FieldTrackerTemplateId.vue";
import FieldCsrfToken from "./creation-fields/FieldCSRFToken.vue";

@Component({
    components: {
        StepLayout,
        StepTwoInfo,
        FieldName,
        FieldShortname,
        FieldDescription,
        FieldTrackerTemplateId,
        FieldCsrfToken
    }
})
export default class StepTwo extends Vue {
    @Mutation
    readonly setCreationFormHasBeenSubmitted!: () => void;

    @State
    readonly has_form_been_submitted!: boolean;

    @Getter
    readonly is_a_duplication!: boolean;

    @Getter
    readonly is_a_xml_import!: boolean;

    @Mutation
    readonly initTrackerNameWithTheSelectedTemplateName!: () => void;

    @Mutation
    readonly initTrackerToBeCreatedFromXml!: () => void;

    @State
    readonly selected_xml_file_input!: HTMLInputElement;

    mounted(): void {
        if (this.is_a_duplication) {
            this.initTrackerNameWithTheSelectedTemplateName();
        } else if (this.is_a_xml_import) {
            this.initTrackerToBeCreatedFromXml();

            const form = this.$refs.tracker_creation_form;

            if (!(form instanceof Element)) {
                return;
            }

            form.appendChild(this.selected_xml_file_input);
        }

        window.addEventListener("beforeunload", this.beforeUnload);
    }

    beforeDestroy(): void {
        window.removeEventListener("beforeunload", this.beforeUnload);
    }

    beforeUnload(event: Event): void {
        if (this.has_form_been_submitted) {
            delete event.returnValue;
        } else {
            event.preventDefault();
            event.returnValue = false;
        }
    }

    get form_enctype(): string {
        if (this.is_a_xml_import) {
            return "multipart/form-data";
        }

        return "application/x-www-form-urlencoded";
    }
}
</script>
