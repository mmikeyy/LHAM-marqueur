import { Component, OnInit, OnDestroy } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { JoueursService } from '../../services/joueursService';
import { CustomValidators } from '../../shared/CustomValidators';
import { IAppState } from '../../store/store';
import { calendar_fr, compact } from '../../types/types';
import * as R from 'ramda';


@Component(
  {
    templateUrl: './nouveauSubstitut.component.html'
  }
)
export class NouveauSubstitutComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public maxNaissance: string;

  public j = {};
  public form: FormGroup;
  public mois = calendar_fr.monthNames;

  constructor(
    public store: NgRedux<IAppState>,
    public fb: FormBuilder,
    public joueursServ: JoueursService,
    public navCtrl: NavController
  ) {
    this.maxNaissance = (new Date().getFullYear() - 18) + '-01-01';
  }

  ngOnInit() {
    this.subs.push();
    this.form = this.fb.group({
      nom: ['', [Validators.required, Validators.minLength(2)]],
      prenom: ['', [Validators.required, Validators.minLength(2)]],
      courriel: ['', CustomValidators.validCourriel],
      sexe: ['M', CustomValidators.validGenre],
      cell: ['', CustomValidators.validTel],
      date_naissance: ['', CustomValidators.validDate],
      no_chandail: ['', Validators.pattern(/^[0-9]+$/)]
    })
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  isError(fld) {
    return !!this.form.controls[fld].errors;
  }

  soumettre() {

    if (!this.form.valid) {
      console.error('ERREUR', this.form);
      return;
    }
    let compactVal = compact(this.form.value);
    console.log('valide', this.form.value, compactVal);
    this.joueursServ.nouveauJoueur(compactVal)
      .then(succes => {
        if (succes) {
          this.navCtrl.pop();
        }
      })
  }

  clrDate() {
    console.log('clr date');
  }

}
