import { Application } from "@hotwired/stimulus";
import { Autocomplete } from "stimulus-autocomplete";

const app = Application.start();
app.register("autocomplete", Autocomplete);
