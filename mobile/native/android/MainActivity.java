package __PACKAGE__;

import android.os.Bundle;
import android.view.View;
import android.view.ViewGroup;
import androidx.core.content.ContextCompat;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;
import com.getcapacitor.BridgeActivity;

/**
 * Fikrlash: sayt tizim panellari (soat, batareya, pastki tugmalar) va klaviatura ostida qolmasin.
 * Android 15+ ilovani butun ekranga yoyadi — WebView chetlaridan panel o‘lchamicha joy qoldiriladi,
 * panellar orqasi esa sayt rangida bo‘yaladi.
 */
public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        final View webView = getBridge().getWebView();
        final View parent = (View) webView.getParent();
        parent.setBackgroundColor(ContextCompat.getColor(this, R.color.fk_bars));

        ViewCompat.setOnApplyWindowInsetsListener(parent, (v, insets) -> {
            Insets bars = insets.getInsets(WindowInsetsCompat.Type.systemBars() | WindowInsetsCompat.Type.displayCutout());
            Insets ime = insets.getInsets(WindowInsetsCompat.Type.ime());
            ViewGroup.MarginLayoutParams lp = (ViewGroup.MarginLayoutParams) webView.getLayoutParams();
            lp.topMargin = bars.top;
            lp.leftMargin = bars.left;
            lp.rightMargin = bars.right;
            lp.bottomMargin = Math.max(bars.bottom, ime.bottom);
            webView.setLayoutParams(lp);
            return WindowInsetsCompat.CONSUMED;
        });
    }
}
