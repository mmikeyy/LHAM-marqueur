import { Component, OnInit, OnDestroy } from '@angular/core';
import { AlertController, NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux } from '@angular-redux/store';
import { JoueursService } from '../../services/joueursService';
import { MatchsService } from '../../services/matchs.service';
import { IAppState } from '../../store/store';
import * as R from 'ramda';
import { ChoixSubstitutComponent } from '../choixSubstitut/choixSubstitut.component';
import { NouveauSubstitutComponent } from '../nouveauSubstitut/nouveauSubstitut.component';

@Component(
  {
    templateUrl: './choixJoueurs.component.html',
    styles: [
      `td, th {
            padding: .2em 1em .2em 0
        }`
     ]

  }
)
export class ChoixJoueursComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];

  constructor(
    public store: NgRedux<IAppState>,
    public joueursServ: JoueursService,
    public matchsServ: MatchsService,
    public alertCtrl: AlertController,
    public navCtrl: NavController
  ) {

  }

  ngOnInit() {
    this.subs.push();
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  positionText(position) {
    switch(position) {
      case 0:
        return 'Avant';
      case 1:
        return 'Défense';
      case 2:
        return 'Gardien';
      case 3:
        return 'Avant/Défense';
      default:
        return '-';
    }
  }

  changePosition(joueur) {
    let alert = this.alertCtrl.create();
    alert.setTitle('Position du joueur');
    let positions = R.range(0,4);
    positions.push(-1);
    for (let pos of positions) {
      alert.addInput({
        type: 'radio',
        label: this.positionText(pos),
        value: pos + '',
        checked: joueur.position == pos
      })
    }
    alert.addButton('Annuler');
    alert.addButton({
      text: 'OK',
      handler: val => {
        joueur.position = parseInt(val);
      }
    });
    alert.present();
  }

  entrerChandail(joueur) {
    let alert = this.alertCtrl.create({
      title: joueur.nom,
      message: 'Entrez son no de chandail',
      inputs: [
        {
          name: 'chandail',
          value: joueur.no_chandail
        }
      ],
      buttons: [
        {
          text: 'Annuler'
        },
        {
          text: 'OK',
          handler: data => {
            joueur.no_chandail = (data['chandail'] || '').replace(/[^0-9]/g, '');
            if (joueur.no_chandail && parseInt(joueur.no_chandail) > 255) {
              joueur.no_chandail = 255;
            }
          }
        }
      ]
    });
    alert.present();
  }

  choisir(joueur) {
    if (joueur.locked && joueur.choisi) {
      return
    }
    joueur.choisi = !joueur.choisi;
    if (joueur.choisi) {
      joueur.id_equipe_match = this.joueursServ.idEquipe;
    }
  }

  refresh() {
    this.joueursServ.refresh();
  }

  ajouterSubstitut() {
    this.navCtrl.push(ChoixSubstitutComponent);
  }
  ajouterMembre() {
    this.navCtrl.push(NouveauSubstitutComponent);
  }
}
